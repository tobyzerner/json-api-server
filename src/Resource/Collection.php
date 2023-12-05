<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

interface Collection
{
    /**
     * Get the collection name.
     */
    public function name(): string;

    /**
     * Get the resources contained within this collection.
     *
     * @return string[]
     */
    public function resources(): array;

    /**
     * Get the name of the resource that represents the given model.
     */
    public function resource(object $model, Context $context): ?string;

    /**
     * The collection's endpoints.
     */
    public function endpoints(): array;
}
