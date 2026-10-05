<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

interface Findable
{
    /**
     * Find the models with the given IDs, in any order. IDs without a model
     * are omitted.
     *
     * @param string[] $ids
     * @return object[]
     */
    public function find(array $ids, Context $context): array;
}
