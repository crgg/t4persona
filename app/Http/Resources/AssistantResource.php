<?php

namespace App\Http\Resources;

use App\Http\Resources\SessionPlainResource;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantResource extends JsonResource
{
    public function toArray($request): array
    {
        $open = $this->openSession;
        $last_session = $this->sessions()->orderBy('date_start','desc')->first();

        $session_history = [];
        if( isset($request['session_history']) && (bool)$request['session_history'] ){
            $session_history = $this->sessions()->orderBy('date_start','desc')->get();
        }

        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'name'             => $this->name,
            'state'            => $this->state,
            'base_personality' => $this->base_personality,
            'date_creation'    => $this->date_creation?->toIso8601String(),

            'open_session'     => $open,
            'last_session'     => $open ? null : $last_session,
            'session_history'  => $session_history
        ];
    }
}
