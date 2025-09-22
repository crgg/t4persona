<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OpenSessionPlainResource extends JsonResource
{
    /**
     * @param  \App\Models\GeneratedSession|array  $resource
     */
    public function toArray($request): array
    {
        // Soporta array stdClass o modelo
        $id           = is_array($this->resource) ? ($this->resource['id'] ?? null) : $this->id;
        $assistant_id = is_array($this->resource) ? ($this->resource['assistant_id'] ?? null) : $this->assistant_id;
        $date_start   = is_array($this->resource) ? ($this->resource['date_start'] ?? null) : $this->date_start;
        $date_end     = is_array($this->resource) ? ($this->resource['date_end'] ?? null) : $this->date_end;

        // Normaliza a ISO 8601 si vienen como Carbon
        $dateStartIso = method_exists($date_start, 'toIso8601String') ? $date_start->toIso8601String() : $date_start;
        $dateEndIso   = method_exists($date_end, 'toIso8601String') ? $date_end->toIso8601String() : $date_end;

        return [
            'id'           => $id,
            'assistant_id' => $assistant_id,
            'date_start'   => $dateStartIso,
            'date_end'     => $dateEndIso,
        ];
    }
}
