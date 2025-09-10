<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id'            => $this->id,
            'assistant_id'  => $this->assistant_id,
            'type'          => $this->type,
            'storage_url'   => $this->storage_url,
            'transcription' => $this->transcription,
            'metadata'      => $this->metadata,
            'date_upload'   => $this->date_upload?->toIso8601String(),
        ];
    }
}
