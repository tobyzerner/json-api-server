<?php

namespace Tobyz\JsonApiServer\Exception\Filter;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class UnknownFilterException extends BadRequestException
{
    public function __construct(public readonly string $filter)
    {
        parent::__construct("Unknown filter: $filter");

        $this->meta(['filter' => $this->filter]);
    }
}
