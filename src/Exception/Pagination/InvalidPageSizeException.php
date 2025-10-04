<?php

namespace Tobyz\JsonApiServer\Exception\Pagination;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidPageSizeException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Page size must be a positive integer');
    }
}
