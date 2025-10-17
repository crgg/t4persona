<?php

namespace App\Http\Controllers;

use App\Models\Interaction;
use App\Models\GeneratedSession; // tabla 'sessions'
use App\Models\Assistant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\InteractionResource;
use App\Http\Resources\InteractionCollection;

class InteractionController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;
    private const MAX_FILE_KB      = 102400; // 100MB

    // GET /interactions?session_id=...&per_page=...
    public function index(Request $request): JsonResponse
    {
        $v = Validator::make($request->query(), [
            'session_id' => ['required','uuid', Rule::exists('sessions','id')],
            'per_page'   => ['nullable','integer','min:1','max:'.self::MAX_PER_PAGE],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);

        $session   = GeneratedSession::where('id', $request->query('session_id'))->firstOrFail();
        $assistant = Assistant::find($session->assistant_id);
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        $perPage = (int) ($request->query('per_page') ?? self::DEFAULT_PER_PAGE);

        $paginator = Interaction::where('session_id', $session->id)
            ->orderBy('timestamp', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json(
            (new InteractionCollection($paginator))->toArray($request)
        );
    }

    /**
     * POST /interactions
     * Crea la interacción con el MENSAJE DEL USUARIO (texto o audio).
     * Regla por sesión:
     *   Si la última interacción (timestamp DESC) NO tiene respuesta (has_response=false) y NO fue cancelada (was_canceled=false),
     *   se bloquea salvo que venga continue=1 (o force=1).
     */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'session_id'         => ['required','uuid', Rule::exists('sessions','id')],
            'text_from_user'     => ['nullable','string'],
            'user_audio_url'     => ['nullable','url'],
            'user_audio_file'    => ['sometimes','file','max:'.self::MAX_FILE_KB,
                                     'mimetypes:audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/x-m4a'],
            'emotion_deteted'    => ['nullable','string','max:100'],
            'timestamp'          => ['nullable','date'],
            'continue'           => ['sometimes','boolean'],
            'force'              => ['sometimes','boolean'],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);

        // No aceptar campos del asistente aquí
        if ($request->hasAny(['assistant_text_response','assistant_text_response_file','assistant_audio_response','assistant_audio_file'])) {
            return response()->json([
                'status'=>false,
                'errors'=>['payload'=>['Use POST /interactions/{id}/respond for assistant reply']],
            ], 422);
        }

        $data = $v->validated();

        $session   = GeneratedSession::where('id', $data['session_id'])->firstOrFail();
        $assistant = Assistant::find($session->assistant_id);
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }
        if ($session->date_end !== null) {
            return response()->json(['status'=>false,'errors'=>['session'=>['This session is already closed']]], 422);
        }

        //##IMPORTANTE - ESTO SE REMOVIO PORQUE EL USUARIO PUEDE ENVIAR CUALQUIER X CANTIDAD DE MENSAJES
        // Gate: bloquear si la última interacción no ha sido respondida y no cancelada
        //$skipGate = $request->boolean('continue') || $request->boolean('force');
        //if (!$skipGate) {
        //    $last = Interaction::where('session_id', $session->id)
        //        ->orderBy('timestamp', 'desc')
        //        ->first();
        //    if ($last && !$last->was_canceled && $last->has_response === false) {
        //        return response()->json([
        //            'status'=>false,
        //            'errors'=>['interaction'=>['Previous message is still pending. Send continue=1 to proceed.']],
        //        ], 409);
        //    }
        //}

        // Subir audio del USUARIO si viene archivo
        if ($request->hasFile('user_audio_file')) {
            $file  = $request->file('user_audio_file');
            $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $key   = 'assistants/'.$assistant->id.'/sessions/'.$session->id.'/interactions/'.(string) Str::uuid().'-user-audio-'.$clean;

            Storage::disk('s3')->putFileAs(
                dirname($key),
                $file,
                basename($key),
                [
                    'visibility'  => 'public',
                    'ContentType' => $file->getClientMimeType() ?: $file->getMimeType(),
                ]
            );
            $data['user_audio_url'] = Storage::disk('s3')->url($key);
        }

        // Debe venir al menos texto o audio del usuario
        if (empty($data['text_from_user']) && empty($data['user_audio_url'])) {
            return response()->json([
                'status'=>false,
                'errors'=>['content'=>['Provide user text_from_user or user_audio_file/user_audio_url']],
            ], 422);
        }

        $row                           = new Interaction();
        $row->id                       = (string) Str::uuid();
        $row->session_id               = $session->id;
        $row->text_from_user           = $data['text_from_user'] ?? null;
        $row->user_audio_url           = $data['user_audio_url'] ?? null;
        $row->assistant_text_response  = null;
        $row->assistant_audio_response = null;
        $row->emotion_deteted          = $data['emotion_deteted'] ?? null;
        $row->timestamp                = $data['timestamp'] ?? now();
        $row->has_response             = false; // para saber si el modelo respondió
        $row->was_canceled             = false; // para saber si fue cancelada
        $row->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Created',
            'data'   => (new InteractionResource($row))->toArray($request),
        ], 201);
    }

    /**
     * GET /interactions/was-canceled?interaction_id=... | ?session_id=...
     * Consulta si una interacción (o la última de la sesión) fue cancelada.
     */
    public function wasCanceled(Request $request): JsonResponse
    {
        $v = Validator::make($request->query(), [
            'interaction_id' => ['nullable','uuid', Rule::exists('interactions','id')],
            'session_id'     => ['nullable','uuid', Rule::exists('sessions','id')],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);

        if (!$request->filled('interaction_id') && !$request->filled('session_id')) {
            return response()->json([
                'status'=>false,
                'errors'=>['query'=>['Provide interaction_id or session_id']],
            ], 422);
        }

        if ($request->filled('interaction_id')) {
            $row = Interaction::where('id', $request->query('interaction_id'))->first();
            if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

            $session   = GeneratedSession::where('id', $row->session_id)->first();
            $assistant = $session ? Assistant::find($session->assistant_id) : null;
            if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
                return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
            }

            return response()->json([
                'status' => true,
                'msg'    => 'OK',
                'data'   => [
                    'interaction_id' => $row->id,
                    'was_canceled'   => (bool) $row->was_canceled,
                    'has_response'   => (bool) $row->has_response,
                    'timestamp'      => $row->timestamp,
                    'interaction'    => (new InteractionResource($row))->toArray($request),
                ],
            ]);
        }

        $session   = GeneratedSession::where('id', $request->query('session_id'))->firstOrFail();
        $assistant = Assistant::find($session->assistant_id);
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        $last = Interaction::where('session_id', $session->id)
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$last) {
            return response()->json([
                'status'=>true,
                'msg'   =>'OK',
                'data'  => [
                    'session_id'       => $session->id,
                    'has_interactions' => false,
                    'was_canceled'     => false,
                    'has_response'     => false,
                ],
            ]);
        }

        return response()->json([
            'status' => true,
            'msg'    => 'OK',
            'data'   => [
                'session_id'     => $session->id,
                'interaction_id' => $last->id,
                'was_canceled'   => (bool) $last->was_canceled,
                'has_response'   => (bool) $last->has_response,
                'timestamp'      => $last->timestamp,
                'interaction'    => (new InteractionResource($last))->toArray($request),
            ],
        ]);
    }

    // GET /interactions/{id}
    public function show(Request $request, string $id): JsonResponse
    {
        $row = Interaction::where('id', $id)->first();
        if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

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

    // DELETE /interactions/{id}
    public function destroy(Request $request, string $id): JsonResponse
    {
        $row = Interaction::where('id', $id)->first();
        if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

        $session   = GeneratedSession::where('id', $row->session_id)->first();
        $assistant = $session ? Assistant::find($session->assistant_id) : null;
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        $row->delete();
        return response()->json(['status'=>true,'msg'=>'Deleted']);
    }

    // POST /interactions/{interaction}/cancel
    public function cancel(Request $request, string $interactionId): JsonResponse
    {
        $row = Interaction::where('id', $interactionId)->first();
        if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

        $session   = GeneratedSession::where('id', $row->session_id)->first();
        $assistant = $session ? Assistant::find($session->assistant_id) : null;
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        if ($row->has_response) {
            return response()->json([
                'status'=>false,
                'errors'=>['interaction'=>['This interaction already has a response and cannot be canceled']],
            ], 422);
        }

        if ($row->was_canceled) {
            return response()->json([
                'status'=>true,
                'msg'   =>'Already canceled',
                'data'  => (new InteractionResource($row))->toArray($request),
            ]);
        }

        $row->was_canceled = true;
        $row->save();

        return response()->json([
            'status'=>true,
            'msg'   =>'Canceled',
            'data'  => (new InteractionResource($row))->toArray($request),
        ]);
    }

    // POST /interactions/cancel-last  (body/query: session_id=UUID)
    public function cancelLast(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'session_id' => ['required','uuid', Rule::exists('sessions','id')],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);

        $session   = GeneratedSession::where('id', $request->input('session_id'))->firstOrFail();
        $assistant = Assistant::find($session->assistant_id);
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        $last = Interaction::where('session_id', $session->id)
            ->where('has_response', false)
            ->where('was_canceled', false)
            ->orderBy('timestamp', 'desc')
            ->first();

        if (!$last) {
            return response()->json([
                'status'=>true,
                'msg'   =>'Nothing to cancel (no pending interaction)',
                'data'  => ['session_id'=>$session->id],
            ]);
        }

        $last->was_canceled = true;
        $last->save();

        return response()->json([
            'status'=>true,
            'msg'   =>'Canceled last pending interaction',
            'data'  => (new InteractionResource($last))->toArray($request),
        ]);
    }
}
