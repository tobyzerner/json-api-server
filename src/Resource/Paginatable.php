<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Pagination\OffsetPagination;

interface Paginatable
{
    /**
     * Paginate the given query.
     */
    public function paginate(object $query, OffsetPagination $pagination): void;
}
