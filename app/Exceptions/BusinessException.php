<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Base Business Logic Exception
 * 
 * Use this for expected business logic errors that should be shown to users
 */
class BusinessException extends Exception
{
    protected int $statusCode = 400;
    protected array $errors = [];

    public function __construct(
        string $message, 
        array $errors = [], 
        int $statusCode = 400
    ) {
        parent::__construct($message);
        $this->errors = $errors;
        $this->statusCode = $statusCode;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'errors' => $this->errors,
        ], $this->statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
