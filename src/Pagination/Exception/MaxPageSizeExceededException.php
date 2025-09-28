<?php

namespace Tobyz\JsonApiServer\Pagination\Exception;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class MaxPageSizeExceededException extends BadRequestException
{
    public function __construct(private readonly int $maxSize)
    {
        parent::__construct('Page size requested is too large.');

        $this->setMeta(['page' => ['maxSize' => $this->maxSize]])->setLinks([
            'type' => [
                'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/max-size-exceeded',
            ],
        ]);
    }
}
