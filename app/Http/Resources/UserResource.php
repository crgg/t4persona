<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => (int)$this->id,
            'name'            => (string) $this->name,
            'alias'           => $this->alias,
            'email'           => (string) $this->email,
            'rol'             => $this->rol,
            'age'             => $this->age !== null ? (int) $this->age : null,
            'country'         => $this->country,
            'language'        => $this->language,
            //'avatar_path'     => $this->avatar_path,
            'avatar_url'      => $this->avatar_path ? Storage::disk('s3')->url($this->avatar_path) : null,
            'date_register'   => optional($this->date_register)->toISOString(),
            'last_login'      => optional($this->last_login)->toISOString(),
            'email_verified_at' => optional($this->email_verified_at)->toISOString(),
        ];
    }
}
