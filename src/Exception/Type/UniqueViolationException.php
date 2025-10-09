<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class UniqueViolationException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Value must contain unique values');
    }
}
