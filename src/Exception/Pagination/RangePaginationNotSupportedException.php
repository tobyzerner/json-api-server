<?php

namespace Tobyz\JsonApiServer\Exception\Pagination;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class RangePaginationNotSupportedException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Range pagination is not supported');

        $this->links([
            'type' => [
                'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/range-pagination-not-supported',
            ],
        ]);
    }
}
