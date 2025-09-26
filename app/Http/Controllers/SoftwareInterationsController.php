<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Assistant;
use App\Models\Interaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\InteractionResource;
use App\Http\Resources\InteractionCollection;
use App\Models\GeneratedSession; // tabla 'sessions'

class SoftwareInterationsController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;
    private const MAX_FILE_KB      = 102400; // 100MB

    // GET /software-interations?session_id=...&per_page=...
    public function index(Request $request): JsonResponse
    {

        $v = Validator::make($request->query(), [
            'session_id' => ['required','uuid', Rule::exists('sessions','id')],
            'per_page'   => ['nullable','integer','min:1','max:'.self::MAX_PER_PAGE],
        ]);

        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }

        $session   = GeneratedSession::where('id', $request->query('session_id'))->firstOrFail();
        $assistant = Assistant::find($session->assistant_id);


        $perPage = (int) ($request->query('per_page') ?? self::DEFAULT_PER_PAGE);

        $paginator = Interaction::where('session_id', $session->id)
            ->orderBy('timestamp', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json(
            (new InteractionCollection($paginator))->toArray($request)
        );
    }

    // GET /software-interations/{id}
    public function show(Request $request, string $id): JsonResponse
    {
        $row = Interaction::where('id', $id)->first();
        if (!$row) {
            return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);
        }

        $session   = GeneratedSession::where('id', $row->session_id)->first();
        $assistant = $session ? Assistant::find($session->assistant_id) : null;
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        return response()->json([
            'status' => true,
            'msg'    => 'OK',
            'data'   => (new InteractionResource($row))->toArray($request),
        ]);
    }

    /**
     * POST /interactions/{interaction}/respond
     * Agrega la RESPUESTA DEL ASISTENTE (texto o audio) a la interacción.
     */
    public function respond(Request $request, string $interactionId): JsonResponse
    {

        $v = Validator::make($request->all(), [
            'assistant_text_response'  => ['nullable','string'],
            'assistant_audio_response' => ['nullable','url'],
            'assistant_audio_file'     => ['sometimes','file','max:'.self::MAX_FILE_KB,
                                           'mimetypes:audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/x-m4a'],
            'emotion_deteted'          => ['nullable','string','max:100'],
            'file_uuid'          => ['nullable','string','max:100'],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        $data = $v->validated();

        $row = Interaction::where('id', $interactionId)->first();
        if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

        $session   = GeneratedSession::where('id', $row->session_id)->first();
        $assistant = $session ? Assistant::find($session->assistant_id) : null;
        if (!$assistant ) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }
        if ($session->date_end !== null) {
            return response()->json(['status'=>false,'errors'=>['session'=>['This session is already closed']]], 422);
        }
        if ($row->cancel) {
            return response()->json([
                'status'=>false,
                'errors'=>['interaction'=>['This interaction has been cancelled']],
            ], 409);
        }

        // Evitar sobre-escritura si ya había respuesta
        if (!empty($row->assistant_text_response) || !empty($row->assistant_audio_response)) {
            return response()->json([
                'status'=>false,
                'errors'=>['interaction'=>['This interaction already has an assistant response']],
            ], 422);
        }

        // Subir audio del ASISTENTE si viene archivo
        if ($request->hasFile('assistant_audio_file')) {
            $file  = $request->file('assistant_audio_file');
            $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $key   = 'assistants/'.$assistant->id.'/sessions/'.$session->id.'/interactions/'.(string) Str::uuid().'-assistant-audio-'.$clean;

            Storage::disk('s3')->putFileAs(
                dirname($key),
                $file,
                basename($key),
                [
                    'visibility'  => 'public',
                    'ContentType' => $file->getClientMimeType() ?: $file->getMimeType(),
                ]
            );
            $data['assistant_audio_response'] = Storage::disk('s3')->url($key);
        }

        // Debe venir al menos texto o audio del asistente
        if (empty($data['assistant_text_response']) && empty($data['assistant_audio_response'])) {
            return response()->json([
                'status'=>false,
                'errors'=>['content'=>['Provide assistant_text_response or assistant_audio_file/assistant_audio_response']],
            ], 422);
        }

        $row->assistant_text_response  = $data['assistant_text_response']  ?? null;
        $row->assistant_audio_response = $data['assistant_audio_response'] ?? null;
        if (array_key_exists('emotion_deteted', $data)) {
            $row->emotion_deteted = $data['emotion_deteted'];
        }
        $row->has_response = true;
        $row->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Assistant response saved',
            'data'   => (new InteractionResource($row))->toArray($request),
        ]);
    }


    public function usersWithOpenSessions(Request $request): JsonResponse
    {
        // Paginación por usuarios
        $perPage = (int) ($request->query('per_page') ?? 25);
        if ($perPage < 1)   $perPage = 1;
        if ($perPage > 200) $perPage = 200;
        $page = (int) ($request->query('page') ?? 1);
        if ($page < 1) $page = 1;

        // Agrupado por usuario: contar sesiones abiertas y ordenar por la última fecha de inicio
        $agg = DB::table('sessions')
            ->join('assistants', 'assistants.id', '=', 'sessions.assistant_id')
            ->whereNull('sessions.date_end')
            ->groupBy('assistants.user_id')
            ->select(
                'assistants.user_id',
                DB::raw('COUNT(*) AS open_sessions_count'),
                DB::raw('MAX(sessions.date_start) AS last_opened_at') // usar fecha, no UUID
            )
            ->orderByDesc('last_opened_at');

        $paginator = $agg->paginate($perPage, ['*'], 'page', $page);

        if ($paginator->isEmpty()) {
            return response()->json([
                'status'      => true,
                'msg'         => 'OK',
                'count_users' => 0,
                'data'        => [],
                'pagination'  => [
                    'total'  => 0,
                    'count'        => 0,
                    'per_page'     => $perPage,
                    'current_page' => $paginator->currentPage(),
                    'total_pages'  => 0,
                ],
            ]);
        }

        // Usuarios de la página actual
        $pageUserIds = collect($paginator->items())->pluck('user_id')->values();
        $users = \App\Models\User::whereIn('id', $pageUserIds)->get()->keyBy('id');

        // Sesiones abiertas de esos usuarios (sin tocar UUID con funciones)
        $sessionsByUser = DB::table('sessions')
            ->join('assistants', 'assistants.id', '=', 'sessions.assistant_id')
            ->whereNull('sessions.date_end')
            ->whereIn('assistants.user_id', $pageUserIds)
            ->orderBy('sessions.date_start', 'desc')
            ->select('sessions.*', 'assistants.user_id')
            ->get()
            ->groupBy('user_id');

        // Armar respuesta
        $data = [];
        foreach ($paginator->items() as $row) {
            $uid = (int) $row->user_id;
            $u   = $users->get($uid);

            $list = ($sessionsByUser->get($uid) ?? collect())->values()->map(function ($s) {
                return [
                    'id'           => $s->id,            // UUID
                    'assistant_id' => $s->assistant_id,  // UUID
                    'date_start'   => $s->date_start,    // timestamp
                    'date_end'     => $s->date_end,      // null (abierta)
                ];
            })->all();

            $data[] = [
                'user' => [
                    'id'    => $uid,
                    'name'  => $u?->name,
                    'email' => $u?->email,
                ],
                'open_sessions_count' => (int) $row->open_sessions_count,
                'open_sessions'       => $list,
            ];
        }

        return response()->json([
            'status'      => true,
            'msg'         => 'OK',
            'count_users' => count($data),
            'data'        => $data,
            'pagination'  => [
                'total'  => $paginator->total(),
                'count'        => $paginator->count(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages'  => $paginator->lastPage(),
            ],
        ]);
    }

}
