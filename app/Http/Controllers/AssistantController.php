<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Resources\AssistantResource;
use App\Http\Resources\AssistantCollection;

class AssistantController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;
    private const MAX_PER_PAGE     = 100;

    // GET /assistants
    public function index(Request $request): JsonResponse
    {
        $perPage    = $this->getPerPage($request);
        $searchTerm = $this->getSearchTerm($request); // ← lee ?search=

        $query = Assistant::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('date_creation', 'desc');

        // Búsqueda por nombre (Postgres ILIKE = case-insensitive)
        if ($searchTerm !== null) {
            $query->where('name', 'ilike', '%' . $searchTerm . '%');
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        // AssistantCollection devuelve { status, data, pagination }
        return response()->json(
            (new AssistantCollection($paginator))->toArray($request)
        );
    }

    // POST /assistants
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:150',
                // único por usuario
                Rule::unique('assistants', 'name')->where(function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                }),
            ],
            'state'            => 'sometimes|string|max:20',
            'base_personality' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $assistant                   = new Assistant();
        $assistant->id               = (string) Str::uuid(); // UUID en PHP
        $assistant->user_id          = $request->user()->id;
        $assistant->name             = trim($data['name']);
        $assistant->state            = array_key_exists('state', $data) ? $data['state'] : 'neutral';
        $assistant->base_personality = array_key_exists('base_personality', $data) ? $data['base_personality'] : null;
        $assistant->date_creation    = now();
        $assistant->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Created',
            'data'   => (new AssistantResource($assistant))->toArray($request),
        ], 201);
    }

    // GET /assistants/{assistant}
    public function show(Request $request, Assistant $assistant): JsonResponse
    {
        $this->assertOwner($request, $assistant);

        return response()->json([
            'status' => true,
            'msg'    => 'OK',
            'data'   => (new AssistantResource($assistant))->toArray($request),
        ]);
    }

    // PUT/PATCH /assistants/{assistant}
    public function update(Request $request, Assistant $assistant): JsonResponse
    {
        $this->assertOwner($request, $assistant);

        $validator = Validator::make($request->all(), [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                Rule::unique('assistants', 'name')
                    ->ignore($assistant->id, 'id')
                    ->where(function ($q) use ($request) {
                        $q->where('user_id', $request->user()->id);
                    }),
            ],
            'state'            => 'sometimes|string|max:20',
            'base_personality' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('name', $data)) {
            $assistant->name = trim($data['name']);
        }
        if (array_key_exists('state', $data)) {
            $assistant->state = $data['state'];
        }
        if (array_key_exists('base_personality', $data)) {
            $assistant->base_personality = $data['base_personality'];
        }

        $assistant->save();

        return response()->json([
            'status' => true,
            'msg'    => 'Updated',
            'data'   => (new AssistantResource($assistant))->toArray($request),
        ]);
    }

    // DELETE /assistants/{assistant}
    public function destroy(Request $request, Assistant $assistant): JsonResponse
    {
        $this->assertOwner($request, $assistant);

        $assistant->delete();

        return response()->json([
            'status' => true,
            'msg'    => 'Deleted',
        ]);
    }

    // -------- Helpers --------

    private function assertOwner(Request $request, Assistant $assistant): void
    {
        if ((int) $assistant->user_id !== (int) $request->user()->id) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'errors' => ['authorization' => ['Forbidden']],
                ], 403)
            );
        }
    }

    private function getPerPage(Request $request): int
    {
        $value = $request->query('per_page');

        if (!is_numeric($value)) {
            return self::DEFAULT_PER_PAGE;
        }

        $value = (int) $value;

        if ($value < 1) {
            return 1;
        }

        if ($value > self::MAX_PER_PAGE) {
            return self::MAX_PER_PAGE;
        }

        return $value;
    }

    // LEE ?search=... (no ?q=...)
    private function getSearchTerm(Request $request): ?string
    {
        $value = $request->query('search'); // ← aquí cambiamos la key

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }
}
