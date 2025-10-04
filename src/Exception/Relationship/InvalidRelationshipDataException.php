<?php

namespace Tobyz\JsonApiServer\Exception\Relationship;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidRelationshipDataException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Relationship data must be a list of identifier objects');
    }
}
