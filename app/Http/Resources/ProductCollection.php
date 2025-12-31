<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    protected $filtersApplied;
    protected $totalFiltered;

    /**
     * Create a new resource collection instance.
     */
    public function __construct($resource, $filtersApplied = [], $totalFiltered = null)
    {
        parent::__construct($resource);
        $this->filtersApplied = $filtersApplied;
        $this->totalFiltered = $totalFiltered;
    }

    /**
     * Transform the resource collection into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => ProductResource::collection($this->collection),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        $meta = [
            'filters_applied' => $this->filtersApplied,
        ];

        if ($this->totalFiltered !== null) {
            $meta['total_filtered'] = $this->totalFiltered;
        }

        return [
            'meta' => $meta,
        ];
    }
}
