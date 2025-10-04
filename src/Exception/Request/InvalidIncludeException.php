<?php

namespace Tobyz\JsonApiServer\Exception\Request;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidIncludeException extends BadRequestException
{
    public function __construct(public readonly string $include)
    {
        parent::__construct("Invalid include: $include");

        $this->meta(['include' => $this->include]);
    }
}
