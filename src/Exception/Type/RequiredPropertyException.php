<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class RequiredPropertyException extends UnprocessableEntityException
{
    public function __construct(string $property)
    {
        parent::__construct("Value must have required property ':property'");

        $this->error['meta'] = ['property' => $property];
    }
}
