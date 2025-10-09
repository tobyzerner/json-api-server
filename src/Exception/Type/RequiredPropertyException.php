<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class RequiredPropertyException extends BadRequestException
{
    public function __construct(string $property)
    {
        parent::__construct("Value must have required property ':property'");

        $this->error['meta'] = ['property' => $property];
    }
}
