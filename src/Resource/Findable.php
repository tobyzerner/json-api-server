<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

interface Findable
{
    /**
     * Find a model with the given ID.
     */
    public function find(string $id, Context $context): ?object;
}
