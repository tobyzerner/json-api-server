<?php

namespace Tobyz\JsonApiServer\Pagination\Exception;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class RangePaginationNotSupportedException extends BadRequestException
{
    public function __construct()
    {
        parent::__construct('Range pagination is not supported.');

        $this->setLinks([
            'type' => [
                'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/range-pagination-not-supported',
            ],
        ]);
    }
}
