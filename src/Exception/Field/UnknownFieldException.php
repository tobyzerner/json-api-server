<?php

namespace Tobyz\JsonApiServer\Exception\Field;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class UnknownFieldException extends BadRequestException
{
    public function __construct(public readonly string $field)
    {
        parent::__construct("Unknown field: $field");

        $this->meta(['field' => $this->field]);
    }
}
