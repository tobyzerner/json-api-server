<?php

namespace Tobyz\JsonApiServer\Extension\Relfield\Exception;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class RelfieldFieldsConflictException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Relative and absolute sparse fieldsets cannot be combined for a type');
    }
}
