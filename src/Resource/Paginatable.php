<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Pagination\Pagination;

interface Paginatable
{
    /**
     * Paginate the given query.
     */
    public function paginate(object $query, Pagination $pagination): void;
}
