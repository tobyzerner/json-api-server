<?php

namespace Tobyz\JsonApiServer\Exception\Field;

use Tobyz\JsonApiServer\Exception\ForbiddenException;

class ReadOnlyFieldException extends ForbiddenException
{
    public function __construct()
    {
        parent::__construct('Field is not writable');
    }
}
