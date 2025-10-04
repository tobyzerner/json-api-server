<?php

namespace Tobyz\JsonApiServer\Exception\Request;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidQueryParameterException extends BadRequestException
{
    public function __construct(public readonly string $parameter)
    {
        parent::__construct("Invalid query parameter: $parameter");

        $this->meta(['parameter' => $this->parameter]);
    }
}
