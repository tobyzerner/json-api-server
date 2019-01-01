<?php

namespace Tobscure\JsonApiServer\Exception;

use RuntimeException;

class UnsupportedMediaTypeException extends RuntimeException
{
    public function __construct(string $type)
    {
        parent::__construct("Can not parse the [$type] media type.");
    }

    public function getStatusCode()
    {
        return 415;
    }
}
