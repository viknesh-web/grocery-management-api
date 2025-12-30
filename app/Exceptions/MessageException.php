<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class MessageException extends Exception
{
    private $data;

    public function __construct($message, $data = null, $code = 400, Throwable $previous = null)
    {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData()
    {
        return $this->data;
    }
}

