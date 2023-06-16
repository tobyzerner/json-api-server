<?php

namespace Tobyz\JsonApiServer\Pagination;

interface PaginationInterface
{
    public function meta(): array;

    public function links(int $count, ?int $total): array;
}
