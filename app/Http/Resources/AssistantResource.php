<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AssistantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'name'             => $this->name,
            'state'            => $this->state,
            'base_personality' => $this->base_personality,
            'date_creation'    => $this->date_creation ? $this->date_creation->toIso8601String() : null,
        ];
    }
}
