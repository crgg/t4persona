<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InteractionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                        => $this->id,
            'session_id'                => $this->session_id,
            'text_from_user'            => $this->text_from_user,
            'user_audio_url'            => $this->user_audio_url,
            'assistant_text_response'   => $this->assistant_text_response,
            'assistant_audio_response'  => $this->assistant_audio_response,
            'emotion_deteted'           => $this->emotion_deteted,
            'timestamp'                 => $this->timestamp?->toIso8601String(),
        ];
    }
}
