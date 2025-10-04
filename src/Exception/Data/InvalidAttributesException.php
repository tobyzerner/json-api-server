<?php

namespace Tobyz\JsonApiServer\Exception\Data;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidAttributesException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Invalid attributes object');
    }
}
