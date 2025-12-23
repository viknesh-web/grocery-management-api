<?php

namespace App\Http\Controllers\API\V1;

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
     * Display a listing of addresses.
     */
    public function index(Request $request): JsonResponse
    {
        if (!config('features.address_field')) {
            return response()->json([
                'message' => 'Address feature is not enabled',
            ], 403);
        }

        $query = Address::with(['creator:id,name,email']);

        if ($request->has('active')) {
            $query->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $query->where('area_name', 'like', "%{$request->search}%");
        }

        $addresses = $query->orderBy('area_name')->get();

        return response()->json([
            'data' => $addresses->map(function ($address) {
                return [
                    'id' => $address->id,
                    'area_name' => $address->area_name,
                    'is_active' => $address->is_active,
                    'created_at' => $address->created_at?->toISOString(),
                ];
            }),
        ], 200);
    }

    /**
     * Get dropdown options (merged from database and API).
     */
    public function dropdown(): JsonResponse
    {
        if (!config('features.address_field')) {
            return response()->json([
                'message' => 'Address feature is not enabled',
            ], 403);
        }

        $options = $this->addressService->getDropdownOptions();

        return response()->json([
            'data' => $options,
        ], 200);
    }

    /**
     * Store a newly created address.
     */
    public function store(Request $request): JsonResponse
    {
        if (!config('features.address_field')) {
            return response()->json([
                'message' => 'Address feature is not enabled',
            ], 403);
        }

        $validated = $request->validate([
            'area_name' => ['required', 'string', 'max:255', 'unique:addresses,area_name'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $address = Address::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        // Clear cache
        $this->addressService->clearCache();

        return response()->json([
            'message' => 'Address created successfully',
            'data' => [
                'id' => $address->id,
                'area_name' => $address->area_name,
                'is_active' => $address->is_active,
            ],
        ], 201);
    }

    /**
     * Update the specified address.
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        if (!config('features.address_field')) {
            return response()->json([
                'message' => 'Address feature is not enabled',
            ], 403);
        }

        $validated = $request->validate([
            'area_name' => ['sometimes', 'required', 'string', 'max:255', 'unique:addresses,area_name,' . $address->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $address->update($validated);

        // Clear cache
        $this->addressService->clearCache();

        return response()->json([
            'message' => 'Address updated successfully',
            'data' => [
                'id' => $address->id,
                'area_name' => $address->area_name,
                'is_active' => $address->is_active,
            ],
        ], 200);
    }

    /**
     * Remove the specified address.
     */
    public function destroy(Address $address): JsonResponse
    {
        if (!config('features.address_field')) {
            return response()->json([
                'message' => 'Address feature is not enabled',
            ], 403);
        }

        $address->delete();

        // Clear cache
        $this->addressService->clearCache();

        return response()->json([
            'message' => 'Address deleted successfully',
        ], 200);
    }

    /**
     * Search UAE areas using Nominatim API.
     */
    public function searchUAE(Request $request): JsonResponse
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



