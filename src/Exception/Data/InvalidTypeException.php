<?php

namespace Tobyz\JsonApiServer\Exception\Data;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidTypeException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Invalid type value');
    }
}
