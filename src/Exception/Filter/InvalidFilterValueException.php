<?php

namespace Tobyz\JsonApiServer\Exception\Filter;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidFilterValueException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Filter value must be a string');
    }
}
