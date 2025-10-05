<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class UniqueViolationException extends UnprocessableEntityException
{
    public function __construct()
    {
        parent::__construct('Value must contain unique values');
    }
}
