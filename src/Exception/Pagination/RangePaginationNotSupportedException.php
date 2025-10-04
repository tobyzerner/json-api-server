<?php

namespace Tobyz\JsonApiServer\Exception\Pagination;

use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Pagination\CursorPagination;

class RangePaginationNotSupportedException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Range pagination is not supported');

        $this->links([
            'type' => [CursorPagination::PROFILE_URI . '/range-pagination-not-supported'],
        ]);
    }
}
