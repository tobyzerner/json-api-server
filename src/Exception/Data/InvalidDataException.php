<?php

namespace Tobyz\JsonApiServer\Exception\Data;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidDataException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Invalid data object');
    }
}
