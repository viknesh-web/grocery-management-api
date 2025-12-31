<?php

namespace App\Exceptions;

class ServiceException extends BusinessException
{
    public function __construct(string $message = 'Service temporarily unavailable')
    {
        parent::__construct(
            message: $message,
            statusCode: 503
        );
    }
}
