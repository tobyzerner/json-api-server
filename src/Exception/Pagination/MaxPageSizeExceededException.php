<?php

namespace Tobyz\JsonApiServer\Exception\Pagination;

use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Pagination\CursorPagination;

class MaxPageSizeExceededException extends BadRequestException
{
    public function __construct(private readonly int $maxSize)
    {
        parent::__construct('Page size requested is too large');

        $this->meta(['page' => ['maxSize' => $this->maxSize]])->links([
            'type' => [CursorPagination::PROFILE_URI . '/max-size-exceeded'],
        ]);
    }
}
