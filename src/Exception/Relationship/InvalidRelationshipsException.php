<?php

namespace Tobyz\JsonApiServer\Exception\Relationship;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidRelationshipsException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Invalid relationships object');
    }
}
