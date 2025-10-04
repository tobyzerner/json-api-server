<?php

namespace Tobyz\JsonApiServer\Exception\Pagination;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidPageOffsetException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Page offset must be a non-negative integer');
    }
}
