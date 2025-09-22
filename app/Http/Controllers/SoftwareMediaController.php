<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Assistant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        $m = Media::where('id',$mediaId)->first();
        if (!$m) {
            return response()->json(['status'=>false,'msg'=>'Media Not Exists'], 404);
        }

        $v = Validator::make($request->all(), [
            'transcription' => ['nullable','string'],
            'metadata'      => ['nullable','array'],
            'merge'         => ['sometimes','boolean'], // si true, fusiona metadata
        ]);
        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }

        $data = $v->validated();

        // Reglas: debe venir al menos transcription o metadata
        $hasTrans = array_key_exists('transcription', $data);
        $hasMeta  = array_key_exists('metadata', $data);
        if (!$hasTrans && !$hasMeta) {
            return response()->json([
                'status'=>false,
                'errors'=>['payload'=>['Provide transcription or metadata']],
            ], 422);
        }

        if ($hasTrans) {
            $m->transcription = $data['transcription'];
        }

        if ($hasMeta) {
            if ($request->boolean('merge') && is_array($m->metadata)) {
                $m->metadata = array_replace_recursive((array) $m->metadata, (array) $data['metadata']);
            } else {
                $m->metadata = $data['metadata']; // reemplazo completo
            }
        }

        $m->save();

        return response()->json([
            'status'=>true,
            'msg'   =>'Enriched',
            'data'  => $this->presentMedia($m),
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
