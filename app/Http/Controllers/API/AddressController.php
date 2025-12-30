<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Address Controller
 * 
 * Handles address management operations including CRUD and UAE address search.
 */
class AddressController extends Controller
{
    protected AddressService $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }  

    /**
     * Search UAE areas using Nominatim API.
     */
    public function searchUAE(Request $request)
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:100'],
        ]);

        $areas = $this->addressService->searchUAEAreas($validated['query']);

        return response()->json([
            'data' => $areas,
        ], 200);
    }
}



