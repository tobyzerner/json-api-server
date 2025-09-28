<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\Page;

interface Paginatable
{
    /**
     * Get a page of results from the given query.
     */
    public function paginate(object $query, int $offset, int $limit, Context $context): Page;
}
