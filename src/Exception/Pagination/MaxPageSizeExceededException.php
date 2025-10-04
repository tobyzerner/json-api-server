<?php

namespace Tobyz\JsonApiServer\Exception\Pagination;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class MaxPageSizeExceededException extends BadRequestException
{
    public function __construct(private readonly int $maxSize)
    {
        parent::__construct('Page size requested is too large');

        $this->meta(['page' => ['maxSize' => $this->maxSize]])->links([
            'type' => [
                'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/max-size-exceeded',
            ],
        ]);
    }
}
