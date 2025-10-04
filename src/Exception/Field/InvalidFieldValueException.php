<?php

namespace Tobyz\JsonApiServer\Exception\Field;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class InvalidFieldValueException extends UnprocessableEntityException
{
    public function __construct(array $error = [])
    {
        parent::__construct();

        $this->error = $error;
    }
}
