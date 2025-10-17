<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserAdminResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => (string) $this->id,
            'name'            => (string) $this->name,
            'email'           => (string) $this->email,
            'rol'             => $this->rol,
        ];
    }
}
