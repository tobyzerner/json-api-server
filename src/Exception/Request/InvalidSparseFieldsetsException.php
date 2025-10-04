<?php

namespace Tobyz\JsonApiServer\Exception\Request;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidSparseFieldsetsException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Sparse fieldsets must be comma-separated strings');
    }
}
