<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

interface Countable extends Listable
{
    /**
     * Count the models for the given query.
     */
    public function count(object $query, Context $context): ?int;
}
