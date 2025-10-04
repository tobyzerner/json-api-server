<?php

namespace Tobyz\JsonApiServer\Exception\Data;

use Tobyz\JsonApiServer\Exception\ConflictException;

class IdConflictException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('ID does not match the resource ID');
    }
}
