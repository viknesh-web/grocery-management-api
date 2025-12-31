<?php

namespace App\Exceptions;

class ValidationException extends BusinessException
{
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct(
            message: $message,
            errors: $errors,
            statusCode: 422
        );
    }
}
