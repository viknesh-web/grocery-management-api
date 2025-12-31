<?php

namespace App\Exceptions;

class UnauthorizedException extends BusinessException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct(
            message: $message,
            statusCode: 403
        );
    }
}
