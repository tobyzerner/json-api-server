<?php

namespace Tobyz\JsonApiServer\Pagination;

class Page
{
    public function __construct(
        public array $results,
        public ?bool $isFirstPage = null,
        public ?bool $isLastPage = null,
    ) {
    }
}
