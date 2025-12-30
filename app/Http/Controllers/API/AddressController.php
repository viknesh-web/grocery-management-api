<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
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
     * Search UAE areas using Geoapify API.
     */
    public function searchUAE(Request $request)
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        try {
            $areas = $this->addressService->searchUAEAreas($validated['query']);
            return ApiResponse::success($areas);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::validationError(
                ['query' => [$e->getMessage()]],
                'Invalid search query'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to search addresses. Please try again later.',
                null,
                500
            );
        }
    }
}



