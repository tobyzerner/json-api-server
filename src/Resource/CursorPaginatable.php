<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\Page;

interface CursorPaginatable
{
    /**
     * Get a page of results from the given query.
     */
    public function cursorPaginate(
        object $query,
        int $size,
        ?string $after,
        ?string $before,
        Context $context,
    ): Page;

    /**
     * Get the cursor for an item.
     */
    public function itemCursor($model, object $query, Context $context): string;
}
