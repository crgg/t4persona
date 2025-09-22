<?php

namespace App\Http\Controllers;

use App\Models\Assistant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Database\QueryException;
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
        $searchTerm = $this->getSearchTerm($request);

        // Validar filtros adicionales (ligero y seguro)
        $v = \Validator::make($request->query(), [
            'state'         => 'sometimes|string',                 // CSV permitido: "neutral,active"
            'created_from'  => 'sometimes|date',
            'created_to'    => 'sometimes|date',
            'sort_by'       => 'sometimes|in:date_creation,name',
            'sort_dir'      => 'sometimes|in:asc,desc',
        ]);
        if ($v->fails()) {
            return response()->json(['status'=>false,'errors'=>$v->errors()], 422);
        }

        $sortBy  = $request->query('sort_by',  'date_creation');
        $sortDir = $request->query('sort_dir', 'desc');

        $query = Assistant::query()
            ->where('user_id', $request->user()->id);

        // Búsqueda por nombre o estado (ILIKE) y también por id exacto
        if ($searchTerm !== null) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name',  'ilike', '%'.$searchTerm.'%')
                ->orWhere('state','ilike', '%'.$searchTerm.'%')
                ->orWhere('id', $searchTerm); // match exacto por UUID si lo pasan
            });
        }

        // Filtrado por uno o varios estados (CSV)
        if ($request->filled('state')) {
            $states = array_filter(array_map('trim', explode(',', (string) $request->query('state'))));
            if (!empty($states)) {
                $query->whereIn('state', $states);
            }
        }

        // Rango por fecha de creación
        if ($request->filled('created_from')) {
            $query->where('date_creation', '>=', $request->query('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->where('date_creation', '<=', $request->query('created_to'));
        }

        // Orden (por defecto: date_creation DESC)
        $query->orderBy($sortBy, $sortDir);

        $paginator = $query->paginate($perPage)->withQueryString();

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
        $assistant->id               = (string) Str::uuid();
        $assistant->user_id          = $request->user()->id;
        $assistant->name             = trim($data['name']);
        $assistant->state            = array_key_exists('state', $data) ? $data['state'] : 'neutral';
        $assistant->base_personality = array_key_exists('base_personality', $data) ? $data['base_personality'] : null;
        $assistant->date_creation    = now();

        try {
            $assistant->save();
        } catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'errors' => ['database' => ['Error creating assistant']],
            ], 500);
        }

        return response()->json([
            'status' => true,
            'msg'    => 'Created',
            'data'   => (new AssistantResource($assistant))->toArray($request),
        ], 201);
    }

    // GET /assistants/{assistant}
    public function show(Request $request, string $assistant ): JsonResponse
    {
        $assistant = $this->getAssistantOrFail($request, $assistant);

        return response()->json([
            'status' => true,
            'msg'    => 'OK',
            'data'   => (new AssistantResource($assistant))->toArray($request),
        ]);
    }

    // PUT/PATCH /assistants/{assistant}
    public function update(Request $request, string $assistant): JsonResponse
    {
        $assistant = $this->getAssistantOrFail($request, $assistant);

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

        try {
            $assistant->save();
        } catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'errors' => ['database' => ['Error updating assistant']],
            ], 500);
        }

        return response()->json([
            'status' => true,
            'msg'    => 'Updated',
            'data'   => (new AssistantResource($assistant))->toArray($request),
        ]);
    }

    // DELETE /assistants/{assistant}
    public function destroy(Request $request, string $assistant): JsonResponse
    {
        $assistant = $this->getAssistantOrFail($request, $assistant);

        try {
            $assistant->delete();
        } catch (QueryException $e) {
            return response()->json([
                'status' => false,
                'errors' => ['database' => ['Error deleting assistant']],
            ], 500);
        }

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

    private function getSearchTerm(Request $request): ?string
    {
        $value = $request->query('search');

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Busca por ID con validación de UUID y control de errores.
     * Lanza HttpResponseException con el JSON apropiado si algo falla.
     */
    private function getAssistantOrFail(Request $request, string $id): Assistant
    {
        if (!Str::isUuid($id)) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'errors' => ['id' => ['Invalid UUID format']],
                ], 422)
            );
        }

        try {
            $assistant = Assistant::where('id', $id)->first();
        } catch (QueryException $e) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'errors' => ['database' => ['Query error']],
                ], 400)
            );
        }

        if (!$assistant) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'errors' => ['assistant' => ['Not found']],
                ], 404)
            );
        }

        $this->assertOwner($request, $assistant);

        return $assistant;
    }
}
