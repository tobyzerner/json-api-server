<?php

namespace Tobyz\JsonApiServer\Exception\Filter;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidFilterParameterException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Filter parameter must be an array');
    }
}
