<?php

namespace Tobyz\JsonApiServer\Exception\Request;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidSortException extends BadRequestException
{
    public function __construct(public readonly string $sort)
    {
        parent::__construct("Invalid sort: $sort");

        $this->meta(['sort' => $this->sort]);
    }
}
