<?php

namespace App\Exceptions;

class ResourceNotFoundException extends BusinessException
{
    public function __construct(string $resource = 'Resource')
    {
        parent::__construct(
            message: "{$resource} not found",
            statusCode: 404
        );
    }
}
