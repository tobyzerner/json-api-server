<?php

namespace Tobyz\JsonApiServer\Pagination\Exception;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class MaxPageSizeExceededException extends BadRequestException
{
    public function __construct(
        private readonly int $maxSize,
        string $message = 'Page size requested is too large',
    ) {
        parent::__construct($message);

        $this->setMeta(['page' => ['maxSize' => $this->maxSize]])->setLinks([
            'type' => [
                'https://jsonapi.org/profiles/ethanresnick/cursor-pagination/max-size-exceeded',
            ],
        ]);
    }
}
