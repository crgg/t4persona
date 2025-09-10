<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Assistant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Exceptions\HttpResponseException;

class MediaController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;
    private const MAX_FILE_KB      = 102400; // 100MB

    // GET /media?assistant_id=...&per_page=&page=
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'per_page'     => ['nullable','integer','min:1','max:'.self::MAX_PER_PAGE],
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'errors'=>$validator->errors()], 422);
        }

        $assistantId = $request->query('assistant_id');
        $assistant   = Assistant::findOrFail($assistantId);
        $this->assertMediaOwnerByAssistant($request, $assistant);

        $perPage = (int) ($request->query('per_page') ?? self::DEFAULT_PER_PAGE);

        $paginator = Media::query()
            ->where('assistant_id', $assistant->id)
            ->orderBy('date_upload', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $items = [];
        foreach ($paginator->items() as $row) {
            $items[] = $this->presentMedia($row);
        }

        return response()->json([
            'status' => true,
            'data' => $items,
            'pagination' => [
                'total'        => $paginator->total(),
                'count'        => $paginator->count(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages'  => $paginator->lastPage(),
            ],
        ]);
    }

    // POST /media  (multipart/form-data)
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'assistant_id'  => ['required','uuid', Rule::exists('assistants','id')],
            'type'          => ['required', Rule::in(['audio','video','image','text'])],
            'file'          => ['required','file','max:'.self::MAX_FILE_KB],
            'transcription' => ['nullable','string'],
            'metadata'      => ['nullable','array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'errors'=>$validator->errors()], 422);
        }
        $data = $validator->validated();

        $assistant = Assistant::findOrFail($data['assistant_id']);
        $this->assertMediaOwnerByAssistant($request, $assistant);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        // Key en S3
        $fileName = preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $key = 'assistants/'.$assistant->id.'/media/'.(string) Str::uuid().'-'.$fileName;


        Storage::disk('s3')->putFileAs(
            dirname($key),
            $file,
            basename($key),
            [
                'visibility'  => 'public',
                'ContentType' => $file->getClientMimeType() ?: $file->getMimeType(),
            ]
        );
        // URL pública normal directamente del disk S3
        $publicUrl = Storage::disk('s3')->url($key);

        $media               = new Media();
        $media->assistant_id = $assistant->id;
        $media->type         = $data['type'];
        $media->storage_url  = $publicUrl;                // guardamos la URL pública
        $media->transcription= $data['transcription'] ?? null;
        $media->metadata     = $data['metadata'] ?? null;
        $media->date_upload  = now();
        $media->save();

        $media->refresh();

        return response()->json([
            'status' => true,
            'msg'    => 'Created',
            'data'   => $this->presentMedia($media),
        ], 201);
    }

    // GET /media/{media}
    public function show(Request $request, Media $media): JsonResponse
    {
        $this->assertMediaOwner($request, $media);

        return response()->json([
            'status' => true,
            'msg'    => 'OK',
            'data'   => $this->presentMedia($media),
        ]);
    }

    // PUT/PATCH /media/{media}
    public function update(Request $request, Media $media): JsonResponse
    {
        $this->assertMediaOwner($request, $media);

        $validator = Validator::make($request->all(), [
            'type'          => ['sometimes', Rule::in(['audio','video','image','text'])],
            'file'          => ['sometimes','file','max:'.self::MAX_FILE_KB],
            'transcription' => ['nullable','string'],
            'metadata'      => ['nullable','array'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>false,'errors'=>$validator->errors()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('file')) {
            // borrar anterior (si podemos derivar la key desde la URL)
            $oldKey = $this->keyFromUrl($media->storage_url);
            if ($oldKey && Storage::disk('s3')->exists($oldKey)) {
                Storage::disk('s3')->delete($oldKey);
            }

            $file = $request->file('file');
            $fileName = preg_replace('/\s+/', '_', $file->getClientOriginalName());
            $newKey   = 'assistants/'.$media->assistant_id.'/media/'.(string) Str::uuid().'-'.$fileName;

            Storage::disk('s3')->putFileAs(
                dirname($newKey),
                $file,
                basename($newKey),
                [
                    'visibility'  => 'public',
                    'ContentType' => $file->getClientMimeType() ?: $file->getMimeType(),
                ]
            );

            $media->storage_url = Storage::disk('s3')->url($newKey);
        }

        if (array_key_exists('type', $data))          { $media->type = $data['type']; }
        if (array_key_exists('transcription', $data)) { $media->transcription = $data['transcription']; }
        if (array_key_exists('metadata', $data))      { $media->metadata = $data['metadata']; }

        $media->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Updated',
            'data'   => $this->presentMedia($media),
        ]);
    }

    // DELETE /media/{media}
    public function destroy(Request $request, Media $media): JsonResponse
    {
        $this->assertMediaOwner($request, $media);

        $key = $this->keyFromUrl($media->storage_url);
        if ($key && Storage::disk('s3')->exists($key)) {
            Storage::disk('s3')->delete($key);
        }

        $media->delete();

        return response()->json(['status' => true, 'msg' => 'Deleted']);
    }

    // ---------- Helpers ----------

    private function presentMedia(Media $m): array
    {
        return [
            'id'            => $m->id,
            'assistant_id'  => $m->assistant_id,
            'type'          => $m->type,
            'storage_url'   => $m->storage_url,
            'transcription' => $m->transcription,
            'metadata'      => $m->metadata,
            'date_upload'   => $m->date_upload ? $m->date_upload->toIso8601String() : null,
        ];
    }

    /** Deriva la key desde una URL pública (usando la base configurada del disk S3 si existe). */
    private function keyFromUrl(?string $url): ?string
    {
        if (!is_string($url) || $url === '') return null;

        // Si configuraste 'url' en el disk S3 (S3_BASE_URL)
        $base = rtrim((string) config('filesystems.disks.s3.url', ''), '/');
        if ($base !== '' && str_starts_with($url, $base . '/')) {
            return ltrim(substr($url, strlen($base)), '/');
        }

        $bucket = (string) config('filesystems.disks.s3.bucket', '');
        $host   = parse_url($url, PHP_URL_HOST) ?? '';
        $path   = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($bucket !== '' && str_starts_with($host, $bucket . '.s3.')) {
            return $path;
        }
        if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
            return substr($path, strlen($bucket) + 1);
        }

        return $path;
    }

    private function assertMediaOwnerByAssistant(Request $request, Assistant $assistant): void
    {
        if ((int) $assistant->user_id !== (int) $request->user()->id) {
            throw new HttpResponseException(
                response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403)
            );
        }
    }

    private function assertMediaOwner(Request $request, Media $media): void
    {
        $assistant = Assistant::find($media->assistant_id);
        if (! $assistant || (int) $assistant->user_id !== (int) $request->user()->id) {
            throw new HttpResponseException(
                response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403)
            );
        }
    }
}
