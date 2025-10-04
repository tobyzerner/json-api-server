<?php

namespace Tobyz\JsonApiServer\Exception\Data;

use Tobyz\JsonApiServer\Exception\ConflictException;

class UnsupportedTypeException extends ConflictException
{
    public function __construct(public readonly string $type)
    {
        parent::__construct("Type not allowed: $type");

        $this->meta(['type' => $this->type]);
    }
}
