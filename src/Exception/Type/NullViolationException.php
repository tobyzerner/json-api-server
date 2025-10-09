<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class NullViolationException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Value must not be null');
    }
}
