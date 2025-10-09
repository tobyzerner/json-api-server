<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class AdditionalPropertyException extends BadRequestException
{
    public function __construct(string $property)
    {
        parent::__construct("Value must not have additional property '$property'");

        $this->error['meta'] = ['property' => $property];
    }
}
