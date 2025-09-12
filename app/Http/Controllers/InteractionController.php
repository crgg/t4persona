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

    public function index(Request $request): JsonResponse
    {
        $v = Validator::make($request->query(), [
            'session_id' => ['required','uuid', Rule::exists('sessions','id')],
            'per_page'   => ['nullable','integer','min:1','max:'.self::MAX_PER_PAGE],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);

        $session = GeneratedSession::where('id', $request->query('session_id'))->firstOrFail();
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
     * STORE: Crea la interacción con el MENSAJE DEL USUARIO (texto o audio).
     * No acepta campos del asistente aquí.
     */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'session_id'         => ['required','uuid', Rule::exists('sessions','id')],
            'text_from_user'     => ['nullable','string'],
            'user_audio_url'     => ['nullable','url'],
            'user_audio_file'    => ['sometimes','file','max:'.self::MAX_FILE_KB,
                                     'mimetypes:audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/x-m4a'],
            // NO se aceptan campos del asistente aquí
            'emotion_deteted'    => ['nullable','string','max:100'],
            'timestamp'          => ['nullable','date'],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);

        // Bloquear si mandan campos del asistente por error
        if ($request->hasAny(['assistant_text_response','assistant_text_response_file','assistant_audio_response','assistant_audio_file'])) {
            return response()->json([
                'status'=>false,
                'errors'=>['payload'=>['Use POST /interactions/{id}/respond for assistant reply']],
            ], 422);
        }

        $data = $v->validated();

        $session = GeneratedSession::where('id', $data['session_id'])->firstOrFail();
        $assistant = Assistant::find($session->assistant_id);
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }
        if ($session->date_end !== null) {
            return response()->json(['status'=>false,'errors'=>['session'=>['This session is already closed']]], 422);
        }

        // Subir audio del USUARIO si viene archivo
        if ($request->hasFile('user_audio_file')) {
            $file = $request->file('user_audio_file');
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

        $row                    = new Interaction();
        $row->id                = (string) Str::uuid();
        $row->session_id        = $session->id;
        $row->text_from_user    = $data['text_from_user'] ?? null;
        $row->user_audio_url    = $data['user_audio_url'] ?? null;
        $row->assistant_text_response   = null;
        $row->assistant_audio_response  = null;
        $row->emotion_deteted   = $data['emotion_deteted'] ?? null;
        $row->timestamp         = $data['timestamp'] ?? now();
        $row->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Created',
            'data'   => (new InteractionResource($row))->toArray($request),
        ], 201);
    }

    /**
     * RESPOND (POST): agrega la RESPUESTA DEL ASISTENTE (texto o audio) a la interacción.
     * Endpoint: POST /interactions/{interaction}/respond
     */
    public function respond(Request $request, string $interactionId): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'assistant_text_response'      => ['nullable','string'],
            'assistant_audio_response'     => ['nullable','url'],
            'assistant_audio_file'         => ['sometimes','file','max:'.self::MAX_FILE_KB,
                                               'mimetypes:audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/x-m4a'],
            'emotion_deteted'              => ['nullable','string','max:100'],
            // no tocamos timestamp; si quieres registrar uno nuevo, agrega response_timestamp en schema
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        $data = $v->validated();

        $row = Interaction::where('id', $interactionId)->first();
        if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

        $session = GeneratedSession::where('id', $row->session_id)->first();
        $assistant = $session ? Assistant::find($session->assistant_id) : null;
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }
        if ($session->date_end !== null) {
            return response()->json(['status'=>false,'errors'=>['session'=>['This session is already closed']]], 422);
        }

        // Evita sobre-escritura si ya había respuesta del asistente
        if (!empty($row->assistant_text_response) || !empty($row->assistant_audio_response)) {
            return response()->json([
                'status'=>false,
                'errors'=>['interaction'=>['This interaction already has an assistant response']],
            ], 422);
        }

        // Subir audio del ASISTENTE si viene archivo
        if ($request->hasFile('assistant_audio_file')) {
            $file = $request->file('assistant_audio_file');
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
        $row->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Assistant response saved',
            'data'   => (new InteractionResource($row))->toArray($request),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $row = Interaction::where('id', $id)->first();
        if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

        $session = GeneratedSession::where('id', $row->session_id)->first();
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

    public function destroy(Request $request, string $id): JsonResponse
    {
        $row = Interaction::where('id', $id)->first();
        if (!$row) return response()->json(['status'=>false,'errors'=>['interaction'=>['Not found']]], 404);

        $session = GeneratedSession::where('id', $row->session_id)->first();
        $assistant = $session ? Assistant::find($session->assistant_id) : null;
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        $row->delete();
        return response()->json(['status'=>true,'msg'=>'Deleted']);
    }
}
