<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AssistantCollection extends ResourceCollection
{
    public $collects = AssistantResource::class;

    public function toArray($request): array
    {

        $paginator = $this->resource;


        $items = [];
        foreach ($this->collection as $item) {
            $items[] = (new AssistantResource($item))->toArray($request);
        }

        return [
            'status' => true,
            'data' => $items,
            'pagination' => [
                'total'        => $paginator->total(),
                'count'        => $paginator->count(),
                'per_page'     => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages'  => $paginator->lastPage(),
            ],
        ];
    }
}
