<?php

namespace App\Helpers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

/**
 * API Response Helper
 * 
 * Provides consistent response formatting for all API endpoints.
 */
class ApiResponse
{
    /**
     * Create a successful response.
     *
     * @param mixed $data
     * @param string|null $message
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function success($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Create a paginated response.
     *
     * @param LengthAwarePaginator $paginator
     * @param array $meta Additional metadata to include
     * @param string|null $message
     * @return JsonResponse
     */
    public static function paginated(LengthAwarePaginator $paginator, array $meta = [], ?string $message = null): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $paginator->items(),
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ], $meta),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, 200);
    }

    /**
     * Create an error response.
     *
     * @param string $message
     * @param mixed $errors Optional error details (validation errors, etc.)
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function error(string $message, $errors = null, int $statusCode = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Create a not found response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    public static function notFound(?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'Resource not found',
            null,
            404
        );
    }

    /**
     * Create an unauthorized response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    public static function unauthorized(?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'Unauthorized',
            null,
            401
        );
    }

    /**
     * Create a forbidden response.
     *
     * @param string|null $message
     * @return JsonResponse
     */
    public static function forbidden(?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'Forbidden',
            null,
            403
        );
    }

    /**
     * Create a validation error response.
     *
     * @param array $errors Validation errors
     * @param string|null $message
     * @return JsonResponse
     */
    public static function validationError(array $errors, ?string $message = null): JsonResponse
    {
        return self::error(
            $message ?? 'Validation failed',
            $errors,
            422
        );
    }
}

<<<<<<< HEAD
=======

>>>>>>> dd9aaaa63ce56d1fddd8e114c36d56a6be33d8fe
