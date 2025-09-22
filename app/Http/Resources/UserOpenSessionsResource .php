<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserOpenSessionsResource extends JsonResource
{
    /**
     * @param array $resource  // ['user'=>[id,name,email], 'open_sessions_count'=>int, 'open_sessions'=>array]
     */
    public function toArray($request): array
    {
        $user = $this->resource['user'] ?? null;

        return [
            'user' => [
                'id'    => $user['id']    ?? null,
                'name'  => $user['name']  ?? null,
                'email' => $user['email'] ?? null,
            ],
            'open_sessions_count' => (int) ($this->resource['open_sessions_count'] ?? 0),
            'open_sessions'       => OpenSessionPlainResource::collection(
                collect($this->resource['open_sessions'] ?? [])
            ),
        ];
    }
}
