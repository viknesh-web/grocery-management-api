<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => CustomerResource::collection($this->collection),
        ];
    }
}
