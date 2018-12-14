<?php

namespace Tobscure\JsonApiServer\Exception;

use Exception;

class MethodNotAllowedException extends \DomainException
{
    public function __construct($message = null, $code = 405, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
