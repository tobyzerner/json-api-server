<?php

namespace Tobyz\JsonApiServer\Exception\Relationship;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidRelationshipException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Invalid relationship object');
    }
}
