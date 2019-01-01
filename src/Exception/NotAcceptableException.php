<?php

namespace Tobscure\JsonApiServer\Exception;

use RuntimeException;

class NotAcceptableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('None of the accepted media types can be provided');
    }

    public function getStatusCode()
    {
        return 406;
    }
}
