<?php

namespace Tobyz\JsonApiServer\Exception\Field;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class RequiredFieldException extends UnprocessableEntityException
{
    public function __construct()
    {
        parent::__construct('Field is required');
    }
}
