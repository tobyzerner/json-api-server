<?php

namespace Tobyz\JsonApiServer\Pagination\Exception;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class RangePaginationNotSupportedException extends BadRequestException
{
    public function __construct(string $message = 'Range pagination is not supported')
    {
        parent::__construct($message);

        $this->setLinks([
            'type' => [
                'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/range-pagination-not-supported',
            ],
        ]);
    }
}
