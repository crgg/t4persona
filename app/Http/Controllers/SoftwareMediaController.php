<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Assistant;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SoftwareMediaController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    // GET /software-media?assistant_id=...&per_page=&page=&pending=&since=
    // Lista medios de un asistente. "pending=1" devuelve los que aún necesitan enriquecimiento.
    public function index(Request $request): JsonResponse
    {
        $v = Validator::make($request->query(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'per_page'     => ['nullable','integer','min:1','max:' . self::MAX_PER_PAGE],
            'pending'      => ['sometimes','boolean'],
            'since'        => ['sometimes','date'], // filtra por date_upload >= since
        ]);
        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }

        $assistant = Assistant::findOrFail($request->query('assistant_id'));
        $perPage   = (int) ($request->query('per_page') ?? self::DEFAULT_PER_PAGE);

        $q = Media::query()->where('assistant_id', $assistant->id);

        if ($request->boolean('pending')) {
            // Pendiente si falta al menos uno de los dos campos
            $q->where(function ($qq) {
                $qq->whereNull('transcription')->orWhereNull('metadata');
            });
        }

        if ($request->filled('since')) {
            $q->where('date_upload', '>=', $request->query('since'));
        }

        $paginator = $q->orderBy('date_upload', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $items = [];
        foreach ($paginator->items() as $row) {
            $items[] = $this->presentMedia($row);
        }

        return response()->json([
            'status' => true,
            'data'   => $items,
            'pagination' => [
                'total'        => $paginator->total(),
                'count'        => $paginator->count(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages'  => $paginator->lastPage(),
            ],
        ]);
    }

    // GET /software-media/{media}
    public function show(Request $request, string $mediaId): JsonResponse
    {
        $m = Media::where('id',$mediaId)->first();
        if (!$m) {
            return response()->json(['status'=>false,'msg'=>'Media Not Exists'], 404);
        }

        return response()->json([
            'status'=>true,
            'msg'   =>'OK',
            'data'  => $this->presentMedia($m),
        ]);
    }

    // PATCH /software-media/{media}/enrich
    // Solo agrega/actualiza transcription y/o metadata. No toca archivo ni storage_url.

    public function enrich(Request $request, string $mediaId): JsonResponse
    {
        $m = Media::where('id', $mediaId)->first();
        if (!$m) return response()->json(['status'=>false,'msg'=>'Media Not Exists'], 404);

        $v = Validator::make($request->all(), [
            'transcription'          => ['nullable','string'],

            // always arrays for add/merge
            'metadata'               => ['sometimes','array'],
            'extra_fields'           => ['sometimes','array'],
            'extra_fields_two'       => ['sometimes','array'],

            // per-field delete lists (arrays of strings: dot-notation supported)
            'metadata_delete'        => ['sometimes','array'],
            'extra_fields_delete'    => ['sometimes','array'],
            'extra_fields_two_delete'=> ['sometimes','array'],
        ]);
        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }
        $data = $v->validated();

        // Must have at least one meaningful input
        $hasTrans = isset($data['transcription']) && $data['transcription'] !== '';
        $hasAdds  = isset($data['metadata']) || isset($data['extra_fields']) || isset($data['extra_fields_two']);
        $hasDels  = isset($data['metadata_delete']) || isset($data['extra_fields_delete']) || isset($data['extra_fields_two_delete']);
        if (!$hasTrans && !$hasAdds && !$hasDels) {
            return response()->json(['status'=>false,'errors'=>['payload'=>['Provide transcription or add/delete arrays']]], 422);
        }

        // Deep merge helper
        $deepMerge = fn(array $base, array $incoming) => array_replace_recursive($base, $incoming);

        if ($hasTrans) {
            $m->transcription = $data['transcription'];
        }

        // ===== ADD / MERGE (replace keys if they exist) =====
        if (array_key_exists('metadata', $data)) {
            $current        = is_array($m->metadata) ? $m->metadata : (array) $m->metadata;
            $m->metadata    = $deepMerge($current, $data['metadata']);
        }
        if (array_key_exists('extra_fields', $data)) {
            $current        = is_array($m->extra_fields) ? $m->extra_fields : (array) $m->extra_fields;
            $m->extra_fields= $deepMerge($current, $data['extra_fields']);
        }
        if (array_key_exists('extra_fields_two', $data)) {
            $current            = is_array($m->extra_fields_two) ? $m->extra_fields_two : (array) $m->extra_fields_two;
            $m->extra_fields_two= $deepMerge($current, $data['extra_fields_two']);
        }

        // ===== DELETE (dot-notation) =====
        if (!empty($data['metadata_delete'])) {
            $arr = is_array($m->metadata) ? $m->metadata : (array) $m->metadata;
            foreach ($data['metadata_delete'] as $path) { Arr::forget($arr, (string) $path); }
            $m->metadata = $arr;
        }
        if (!empty($data['extra_fields_delete'])) {
            $arr = is_array($m->extra_fields) ? $m->extra_fields : (array) $m->extra_fields;
            foreach ($data['extra_fields_delete'] as $path) { Arr::forget($arr, (string) $path); }
            $m->extra_fields = $arr;
        }
        if (!empty($data['extra_fields_two_delete'])) {
            $arr = is_array($m->extra_fields_two) ? $m->extra_fields_two : (array) $m->extra_fields_two;
            foreach ($data['extra_fields_two_delete'] as $path) { Arr::forget($arr, (string) $path); }
            $m->extra_fields_two = $arr;
        }

        $m->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Enriched',
            'data'   => $this->presentMedia($m),
        ]);
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
}
