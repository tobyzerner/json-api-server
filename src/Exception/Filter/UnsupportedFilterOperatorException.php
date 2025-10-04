<?php

namespace Tobyz\JsonApiServer\Exception\Filter;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class UnsupportedFilterOperatorException extends BadRequestException
{
    public function __construct(public readonly string $operator)
    {
        parent::__construct("Unsupported operator: $operator");

        $this->meta(['operator' => $this->operator]);
    }
}
