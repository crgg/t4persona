<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Media;
use App\Models\Assistant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use App\Models\WhatsappConversation;

use Illuminate\Support\Facades\Storage;
use App\Http\Resources\WhatsappConversationResource;
use App\Http\Resources\WhatsappConversationCollection;

class WhatsappConversationZipController extends Controller
{

    public function store_whatsapp_zip(Request $request): JsonResponse
    {
        $v = \Validator::make($request->all(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'file'         => ['required','file','mimes:zip','mimetypes:application/zip,application/x-zip-compressed'],
        ]);
        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }
        $data = $v->validated();

        $assistant = Assistant::findOrFail($data['assistant_id']);

        /** @var UploadedFile $file */
        $file    = $request->file('file');
        $tmpPath = $file->getRealPath();

        // --- open ZIP and find _chat.txt ---
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return response()->json(['status'=>false,'errors'=>['file'=>['_zip open failed']]], 422);
        }
        $chatIndex = -1;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $ln   = strtolower($name);
            if ($ln === '_chat.txt' || str_ends_with($ln, '/_chat.txt')) { $chatIndex = $i; break; }
        }
        if ($chatIndex === -1) {
            $zip->close();
            return response()->json(['status'=>false,'errors'=>['file'=>['ZIP must include _chat.txt']]], 422);
        }
        $chat = $zip->getFromIndex($chatIndex);
        if ($chat === false) {
            $zip->close();
            return response()->json(['status'=>false,'errors'=>['file'=>['Unable to read _chat.txt']]], 422);
        }

        // --- normalize to UTF-8 ---
        $enc = mb_detect_encoding($chat, ['UTF-8','UTF-16','UTF-16LE','UTF-16BE','ISO-8859-1','Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') { $chat = mb_convert_encoding($chat, 'UTF-8', $enc); }
        if (substr($chat, 0, 3) === "\xEF\xBB\xBF") { $chat = substr($chat, 3); }

        // --- upload ZIP to S3 (original) ---
        $fileName = preg_replace('/\s+/', '_', $file->getClientOriginalName());
        $baseDir  = 'assistants/'.$assistant->id.'/whatsapp_conversation';
        $zipKey   = $baseDir.'/'.(string) Str::uuid().'-'.$fileName;

        Storage::disk('s3')->putFileAs(
            dirname($zipKey),
            $file,
            basename($zipKey),
            ['visibility'=>'public', 'ContentType'=>$file->getClientMimeType() ?: $file->getMimeType()]
        );

        // --- create conversation row ---
        $metadata = [
            'source'         => 'whatsapp_zip',
            'zip_original'   => $file->getClientOriginalName(),
            'raw_text_bytes' => strlen($chat),
        ];

        $wc = WhatsappConversation::create([
            'assistant_id' => $assistant->id,
            'zip_aws_path' => $zipKey,
            'conversation' => $chat,         // si prefieres guardarlo como JSON, cámbialo a array/obj y el cast en el Model
            'metadata'     => $metadata,
        ]);

        // --- iterate and upload every media file inside ZIP ---
        $createdMedia = [];
        $mediaBaseDir = 'assistants/'.$assistant->id.'/media/whatsapp/'.$wc->id;

        // utilidades
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $detectMime = function (string $bytes, ?object $fi) {
            if ($fi) {
                $m = @finfo_buffer($fi, $bytes);
                if (is_string($m) && $m !== 'application/octet-stream') return $m;
            }
            // fallback por extensión dentro de S3
            return 'application/octet-stream';
        };
        $mapType = function (string $mime, string $name) {
            $mime = strtolower($mime);
            if (str_starts_with($mime, 'image/')) return 'image';
            if (str_starts_with($mime, 'video/')) return 'video';
            if (str_starts_with($mime, 'audio/')) return 'audio';
            if (str_starts_with($mime, 'text/'))  return 'text';
            // por extensión (pdf -> text)
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['txt','srt','vtt','csv','log','json','md'])) return 'text';
            if ($ext === 'pdf') return 'text';
            // por si WhatsApp mete archivos "dat" o sin extensión
            return 'text';
        };

        // nombres a ignorar
        $ignoreNames = [
            '__MACOSX/', '.DS_Store', 'Thumbs.db'
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($i === $chatIndex) continue; // saltar _chat.txt

            $name = $zip->getNameIndex($i);
            if (!$name || str_ends_with($name, '/')) continue; // carpeta
            $bn = basename($name);
            if ($bn === '' || in_array($bn, $ignoreNames, true)) continue;
            foreach ($ignoreNames as $ign) {
                if (str_starts_with($name, $ign) || str_contains($name, '/'.$ign)) {
                    continue 2;
                }
            }

            $bytes = $zip->getFromIndex($i);
            if ($bytes === false) continue;

            // detectar mime & tipo
            $mime = $detectMime($bytes, $finfo);
            $type = $mapType($mime, $name);

            // subir a S3
            $safeBn = preg_replace('/[^\w\.\-]+/u', '_', $bn);
            $s3key  = $mediaBaseDir.'/'.(string) Str::uuid().'-'.$safeBn;

            Storage::disk('s3')->put($s3key, $bytes, [
                'visibility'  => 'public',
                'ContentType' => $mime ?: 'application/octet-stream',
            ]);

            $publicUrl = Storage::disk('s3')->url($s3key);

            // crear media row (UUID único)
            do { $mid = (string) Str::uuid(); } while (Media::whereKey($mid)->exists());

            $media = new Media();
            $media->id                    = $mid;
            $media->assistant_id          = $assistant->id;
            $media->type                  = $type;              // 'image'|'audio'|'video'|'text'
            $media->storage_url           = $publicUrl;
            $media->transcription         = null;
            $media->metadata              = [
                'source'                 => 'whatsapp_zip_media',
                'zip_path'               => $name,             // ruta interna dentro del ZIP
                'mime'                   => $mime,
                'size'                   => strlen($bytes),
                //'conversation_id'        => ,
                'original_zip'           => $file->getClientOriginalName(),
            ];
            //$media->extra_fields          = null;               // si ocupas estos campos custom
            //$media->extra_fields_two      = null;
            $media->whatsapp_media_file_id= $wc->id;
            $media->date_upload           = now();
            $media->save();

            // resumen para respuesta (usa tu presentador si existe)
            if (method_exists($this, 'presentMedia')) {
                $createdMedia[] = $this->presentMedia($media);
            } else {
                $createdMedia[] = [
                    'id'           => $media->id,
                    'type'         => $media->type,
                    'storage_url'  => $media->storage_url,
                    'mime'         => $mime,
                    'zip_path'     => $name,
                    'date_upload'  => optional($media->date_upload)->toIso8601String(),
                ];
            }
        }

        if ($finfo) { @finfo_close($finfo); }
        $zip->close();

        return response()->json([
            'status' => true,
            'msg'    => 'WhatsApp ZIP saved; _chat.txt + media uploaded',
            'data'   => [
                'id'              => $wc->id,
                'assistant_id'    => $wc->assistant_id,
                'zip_aws_path'    => $wc->zip_aws_path,
                'media_created'   => $createdMedia,
                'media_count'     => count($createdMedia),
            ],
        ], 201);
    }

        // GET /api/whatsapp-conversations
    public function index(Request $request): JsonResponse
    {
        $v = \Validator::make($request->all(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'per_page'     => ['sometimes','integer','min:1','max:200'],
            'page'         => ['sometimes','integer','min:1'],
        ]);

        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }

        $assistantId = $request->input('assistant_id');

        $q = WhatsappConversation::query()
            ->where('assistant_id', $assistantId)
            ->orderByDesc('created_at');

        $perPage = (int) $request->get('per_page', 25);
        $items   = $q->paginate($perPage);

        $collection = new WhatsappConversationCollection($items);
        return response()->json($collection->toArray($request));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $wc = WhatsappConversation::findOrFail($id);

        // Build a direct/temporary URL to download the original ZIP (best effort)
        $zipUrl = null;
        try {
            if (method_exists(Storage::disk('s3'), 'temporaryUrl')) {
                $zipUrl = Storage::disk('s3')->temporaryUrl($wc->zip_aws_path, now()->addMinutes(30));
            } elseif (method_exists(Storage::disk('s3'), 'url')) {
                $zipUrl = Storage::disk('s3')->url($wc->zip_aws_path);
            }
        } catch (\Throwable $e) {
            $zipUrl = null;
        }

        // Return FULL raw text only — no parsing
        $raw = $wc->conversation;
        if (!is_string($raw)) {
            // If stored as array/object, return as JSON text (unescaped, no parsing)
            $raw = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $bytes = function_exists('mb_strlen')
            ? mb_strlen($raw ?? '', '8bit')
            : strlen($raw ?? '');

        return response()->json([
            'status' => true,
            'data'   => [
                'id'           => (string) $wc->id,
                'assistant_id' => (string) $wc->assistant_id,
                'zip_aws_path' => $wc->zip_aws_path,
                'zip_download' => $zipUrl,
                'metadata'     => $wc->metadata ?? [],
                'created_at'   => optional($wc->created_at)->toIso8601String(),
                'updated_at'   => optional($wc->updated_at)->toIso8601String(),

                // 🔥 Texto COMPLETO sin parsear
                'text'         => $raw,
                'byte_size'    => $bytes,
            ],
        ]);
    }



    public function destroy(string $id): JsonResponse
    {
        $wc = WhatsappConversation::findOrFail($id);

        $deletedMediaRows  = 0;
        $deletedMediaFiles = 0;

        // (1) Borrar MEDIA asociados:
        // Regla de enlace (según tu store): whatsapp_media_file_id = conversation_id
        // Además, si existe zip_original en metadata, filtramos por ese ZIP para mayor precisión.
        $zipOriginal = is_array($wc->metadata ?? null) ? ($wc->metadata['zip_original'] ?? null) : null;

        $mediaQuery = Media::query()
            ->where('assistant_id', $wc->assistant_id)
            ->where('whatsapp_media_file_id', (string) $wc->id);

        if ($zipOriginal) {
            // Postgres JSONB: metadata->>'original_zip' = $zipOriginal
            $mediaQuery->whereRaw("(metadata->>'original_zip') = ?", [$zipOriginal]);
        }

        $mediaList = $mediaQuery->get();

        foreach ($mediaList as $m) {
            // Borrar archivo en S3 (best-effort)
            $s3Key = null;

            // 1) Si guardaste la key en metadata:
            if (is_array($m->metadata ?? null) && !empty($m->metadata['s3_key'])) {
                $s3Key = $m->metadata['s3_key'];
            }

            // 2) Fallback: derivar la key desde storage_url
            if (!$s3Key && !empty($m->storage_url)) {
                $s3Key = $this->deriveS3KeyFromUrl($m->storage_url);
            }

            if ($s3Key) {
                try {
                    if (Storage::disk('s3')->delete($s3Key)) {
                        $deletedMediaFiles++;
                    }
                } catch (\Throwable $e) {
                    // ignorar errores de S3
                }
            }

            // Borrar fila de DB
            try {
                $m->delete();
                $deletedMediaRows++;
            } catch (\Throwable $e) {
                // ignorar
            }
        }

        // (2) Intentar borrar el prefijo/carpeta de esta conversación por si quedaron residuos
        // (este es un best-effort; no falla si no existe)
        try {
            $prefix = "assistants/{$wc->assistant_id}/media/whatsapp/{$wc->id}";
            Storage::disk('s3')->deleteDirectory($prefix);
        } catch (\Throwable $e) {
            // ignorar
        }

        // (3) Borrar el ZIP original de S3 (best-effort)
        if (!empty($wc->zip_aws_path)) {
            try {
                Storage::disk('s3')->delete($wc->zip_aws_path);
            } catch (\Throwable $e) {
                // ignorar
            }
        }

        // (4) Borrar la conversación
        $wc->delete();

        return response()->json([
            'status' => true,
            'msg'    => 'Conversation and media deleted',
            'data'   => [
                'deleted_media_rows'  => $deletedMediaRows,
                'deleted_media_files' => $deletedMediaFiles,
            ],
        ]);
    }

    /**
     * Deriva la S3 key desde una URL pública.
     * Funciona con estilos path-style y virtual-hosted-style.
     */
    protected function deriveS3KeyFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = ltrim($path, '/');

        // Si la URL es del tipo https://s3.amazonaws.com/{bucket}/{key}
        // o https://s3.region.amazonaws.com/{bucket}/{key}
        $bucket = env('AWS_BUCKET');
        if ($bucket && str_starts_with($path, $bucket.'/')) {
            return substr($path, strlen($bucket) + 1);
        }

        // Si ya es solo la key:
        return $path !== '' ? $path : null;
    }


}
