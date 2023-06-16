<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;
use Tobyz\JsonApiServer\Schema\Sort;

interface Listable
{
    /**
     * Create a query object for the current request.
     */
    public function query(Context $context): object;

    /**
     * Get results from the given query.
     */
    public function results(object $query, Context $context): array;

    /**
     * Filters that can be applied to the resource list.
     *
     * @return Filter[]
     */
    public function filters(): array;

    /**
     * Sorts that can be applied to the resource list.
     *
     * @return Sort[]
     */
    public function sorts(): array;
}
