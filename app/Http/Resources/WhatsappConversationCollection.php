<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class WhatsappConversationCollection extends ResourceCollection
{
    public $collects = WhatsappConversationResource::class;

    public function toArray($request): array
    {
        $paginator = $this->resource;

        $items = [];
        foreach ($this->collection as $wc) {
            $items[] = (new WhatsappConversationResource($wc))->toArray($request);
        }

        return [
            'status' => true,
            'data' => $items,
            'pagination' => [
                'total'        => method_exists($paginator, 'total') ? $paginator->total() : count($items),
                'count'        => method_exists($paginator, 'count') ? $paginator->count() : count($items),
                'per_page'     => method_exists($paginator, 'perPage') ? $paginator->perPage() : count($items),
                'current_page' => method_exists($paginator, 'currentPage') ? $paginator->currentPage() : 1,
                'total_pages'  => method_exists($paginator, 'lastPage') ? $paginator->lastPage() : 1,
            ],
        ];
    }
}