<?php

namespace App\Http\Resources;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;



class AssistantMiniResource extends JsonResource
{
    public function toArray($request): array
    {
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
            'date_creation'    => $this->date_creation?->toIso8601String(),
        ];
    }
}
