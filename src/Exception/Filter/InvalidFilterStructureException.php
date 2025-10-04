<?php

namespace Tobyz\JsonApiServer\Exception\Filter;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidFilterStructureException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Filter structure is invalid');
    }
}
