<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class AdditionalPropertyException extends UnprocessableEntityException
{
    public function __construct(string $property)
    {
        parent::__construct("Value must not have additional property '$property'");

        $this->error['meta'] = ['property' => $property];
    }
}
