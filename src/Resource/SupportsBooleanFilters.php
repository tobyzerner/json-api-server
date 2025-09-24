<?php

namespace Tobyz\JsonApiServer\Resource;

interface SupportsBooleanFilters
{
    /**
     * @param callable[] $clauses
     */
    public function filterOr(object $query, array $clauses): void;

    /**
     * @param callable[] $clauses
     */
    public function filterNot(object $query, array $clauses): void;
}
