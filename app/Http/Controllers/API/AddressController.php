<?php

namespace App\Http\Controllers\API;

use App\Exceptions\ServiceException;
use App\Exceptions\ValidationException;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Address\SearchUAERequest;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;

/**
 * Address Controller
 */
class AddressController extends Controller
{
    public function __construct(
        private AddressService $addressService
    ) {}

    /**
     * Search UAE areas using Geoapify API.
     *
     * @param SearchUAERequest $request
     * @return JsonResponse
     */
    public function searchUAE(SearchUAERequest $request): JsonResponse
    {
        try {
            $areas = $this->addressService->searchUAEAreas($request->input('query'));
            return ApiResponse::success($areas);
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->getErrors(), $e->getMessage());
        } catch (ServiceException $e) {
            return ApiResponse::error($e->getMessage(), null, 500);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Unable to search addresses. Please try again later.',
                null,
                500
            );
        }
    }
}
