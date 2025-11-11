<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;



    /**
     *
     *                     "id": 2,
                    "assistant_id": "c0551e83-a37d-4776-a224-478dc58b60c1",
                    "instrument": "BFI-44",
  "items_expected": 44,
                    "items_answered": 44,
                    "completed_at": "2025-09-22T22:12:36.000000Z",
                    "openness_avg": "4.800",
                    "conscientiousness_avg": "3.667",
                    "extraversion_avg": "2.000",
                    "agreeableness_avg": "4.222",
                    "neuroticism_avg": "1.500",
                    "created_at": "2025-09-22T22:12:36.000000Z",
                    "updated_at": "2025-09-22T22:12:36.000000Z"
     *
     */


class AssistantResource extends JsonResource
{

    CONST SELECT_FROM_ANSWERS = [
        'id','instrument','items_expected','items_answered','completed_at',
        'openness_avg',
        'conscientiousness_avg',
        'extraversion_avg',
        'agreeableness_avg',
        'neuroticism_avg',
        'created_at',
        'updated_at',
    ];


    public function toArray($request): array
    {


        $open         = $this->openSession;
        $last_session = $this->sessions()->orderBy('date_start','desc')->first();

        // Por defecto (sin historia o desactivada)
        $session_history             = [];
        $session_history_pagination  = null;

        // Si piden historia con paginación (?session_history=1)
        $wantHistory = $request->has('session_history') &&
                       filter_var($request->query('session_history'), FILTER_VALIDATE_BOOLEAN);

        if ($wantHistory) {
            // per_page con límites 1..100 (default 15)
            $perPage = (int) ($request->query('per_page') ?? 15);
            if ($perPage < 1)   { $perPage = 1; }
            if ($perPage > 100) { $perPage = 100; }

            $paginator = $this->sessions()
                ->orderBy('date_start','desc')
                ->paginate($perPage)
                ->withQueryString(); // conserva ?session_history, ?per_page, ?page

            // Si tienes un resource para sesión, úsalo. Si no, devuelve los modelos tal cual.
            // Descomenta la línea de SessionPlainResource si existe tu clase.
            // $session_history = SessionPlainResource::collection(collect($paginator->items()))->toArray($request);
            $session_history = collect($paginator->items())->map(function ($s) {
                return [
                    'id'           => $s->id,
                    'assistant_id' => $s->assistant_id,
                    'date_start'   => optional($s->date_start)->toIso8601String(),
                    'date_end'     => optional($s->date_end)->toIso8601String(),
                ];
            })->all();

            $session_history_pagination = [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ];
        }

        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'name'             => $this->name,
            'state'            => $this->state,
            'age'              => $this->age,
            'avatar_path'      =>  isset($this->avatar_path) ?  Storage::disk('s3')->url($this->avatar_path) : $this->avatar_path,
            'family_relationship'  => $this->family_relationship,
            'alias'             => $this->alias,
            'country'           => $this->country,
            'language'          => $this->language,
            'base_personality'  => $this->base_personality,
            'date_creation'     => Carbon::parse($this->date_creation)->format('m/d/Y H:i:s') ,
            'death_date'        => is_null($this->death_date) ? null : Carbon::parse($this->death_date)->format('m/d/Y'),
            'birth_date'        => is_null($this->birth_date) ? null : Carbon::parse($this->birth_date)->format('m/d/Y'),
            'open_session'     => $open,
            'last_session'     => $open ? null : $last_session,

            // Historia paginada (solo si se pidió)
            'session_history'            => $session_history,
            'session_history_pagination' => $session_history_pagination,
            'big_five_answers' => $this->answers->select(self::SELECT_FROM_ANSWERS)
        ];
    }


}
