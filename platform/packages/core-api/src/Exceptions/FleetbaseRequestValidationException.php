<?php

namespace GridX\Exceptions;

use Illuminate\Support\MessageBag;

class GridXRequestValidationException extends \Exception implements \Throwable
{
    protected array|MessageBag $errors   = [];

    public function __construct(array|MessageBag $errors = [], $message = 'Invalid request', $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors instanceof MessageBag ? $this->errors->all() : (array) $this->errors;
    }
}
