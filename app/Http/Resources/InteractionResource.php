<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InteractionResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                        => $this->id,
            'session_id'                => $this->session_id,

            'text_from_user'            => $this->text_from_user,
            'user_audio_url'            => $this->user_audio_url,

            'assistant_text_response'   => $this->assistant_text_response,
            'assistant_audio_response'  => $this->assistant_audio_response,
            'assistant_image_response'  => $this->assistant_image_response,

            'emotion_deteted'           => $this->emotion_deteted,
            'timestamp'                 => $this->timestamp,
            'has_response'              => (bool) $this->has_response,
            'was_canceled'              => (bool) $this->was_canceled,

            'file_uuid'                 => $this->file_uuid,
            'file_respond'              => $this->file_respond,
        ];
    }
}
