<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SessionPlainResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'assistant_id' => $this->assistant_id,
            'date_start'   => $this->date_start?->toIso8601String(),
            'date_end'     => $this->date_end?->toIso8601String(),
            'canal'        => $this->canal,

            'assistant'    => new AssistantResource($this->assistant),
        ];
    }
}
