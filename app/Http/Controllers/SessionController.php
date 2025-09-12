<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use App\Models\GeneratedSession;
use App\Models\Interaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Http\Resources\SessionResource;
use App\Http\Resources\InteractionResource;

class SessionController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'assistant_id' => ['required','uuid', Rule::exists('assistants','id')],
            'canal'        => ['sometimes','string','max:20'],
        ]);
        if ($v->fails()) return response()->json(['status'=>false,'errors'=>$v->errors()], 422);

        $assistant = Assistant::findOrFail($request->input('assistant_id'));
        if ((int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        $open = GeneratedSession::where('assistant_id',$assistant->id)->whereNull('date_end')->first();
        if ($open) {
            return response()->json(['status'=>false,'errors'=>['session'=>['This assistant already has an open session'] , 'data' => $open ]], 422);
        }

        $s               = new GeneratedSession();
        $s->id           = (string) Str::uuid();
        $s->user_id      = $request->user()->id;
        $s->assistant_id = $assistant->id;
        $s->date_start   = now();
        $s->date_end     = null;
        $s->canal        = $request->input('canal', 'text');
        $s->save();
        $s->load('assistant');

        return response()->json([
            'status' => true,
            'msg'    => 'Session started',
            'data'   => (new SessionResource($s))->toArray($request),
        ], 201);
    }

    public function end(Request $request, string $sessionId): JsonResponse
    {
        $s = GeneratedSession::where('id',$sessionId)->first();
        if (!$s) return response()->json(['status'=>false,'errors'=>['session'=>['Not found']]], 404);

        $assistant = Assistant::find($s->assistant_id);
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        if ($s->date_end !== null) {
            return response()->json(['status'=>false,'errors'=>['session'=>['This session is already closed']]], 422);
        }

        $s->date_end = now();
        $s->save();
        $s->load('assistant');

        return response()->json([
            'status' => true,
            'msg'    => 'Session ended',
            'data'   => (new SessionResource($s))->toArray($request),
        ]);
    }

    // SHOW con interactions (y paginación de interactions)
    public function show(Request $request, string $sessionId): JsonResponse
    {
        $s = GeneratedSession::with('assistant')->where('id',$sessionId)->first();
        if (!$s) return response()->json(['status'=>false,'errors'=>['session'=>['Not found']]], 404);

        $assistant = $s->assistant;
        if (!$assistant || (int)$assistant->user_id !== (int)$request->user()->id) {
            return response()->json(['status'=>false,'errors'=>['authorization'=>['Forbidden']]], 403);
        }

        $perPage = (int) ($request->query('per_page') ?? 50);
        if ($perPage < 1)   $perPage = 1;
        if ($perPage > 200) $perPage = 200;

        $paginator = Interaction::where('session_id',$s->id)
            ->orderBy('timestamp','asc')
            ->paginate($perPage)
            ->withQueryString();

        $items = [];
        foreach ($paginator->items() as $it) {
            $items[] = (new InteractionResource($it))->toArray($request);
        }

        return response()->json([
            'status' => true,
            'msg'    => 'OK',
            'data'   => array_merge(
                (new SessionResource($s))->toArray($request),
                [
                    'interactions' => $items,
                    'interactions_pagination' => [
                        'total'        => $paginator->total(),
                        'count'        => $paginator->count(),
                        'per_page'     => $paginator->perPage(),
                        'current_page' => $paginator->currentPage(),
                        'total_pages'  => $paginator->lastPage(),
                    ],
                ]
            ),
        ]);
    }
}
