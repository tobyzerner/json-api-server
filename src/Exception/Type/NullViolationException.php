<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class NullViolationException extends UnprocessableEntityException
{
    public function __construct()
    {
        parent::__construct('Value must not be null');
    }
}
