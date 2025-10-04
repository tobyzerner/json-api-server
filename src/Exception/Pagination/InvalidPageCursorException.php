<?php

namespace Tobyz\JsonApiServer\Exception\Pagination;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidPageCursorException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Page cursor invalid');
    }
}
