<?php

namespace Tobyz\JsonApiServer\Exception\Data;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidIdException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Invalid ID value');
    }
}
