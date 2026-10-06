<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Resource\Findable;

trait FindsResources
{
    /**
     * Find a resource within the API.
     *
     * @throws ResourceNotFoundException if the resource is not found.
     */
    private function findResource(Context $context, string $id)
    {
        return (
            $this->findResources($context, [$id])[$id] ?? throw new ResourceNotFoundException(
                $context->collection->name(),
                $id,
            )
        );
    }

    /**
     * Find resources within the API, keyed by ID. IDs that aren't found are
     * omitted.
     */
    protected function findResources(Context $context, array $ids): array
    {
        $collection = $context->collection;

        if (!$collection instanceof Findable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Findable::class),
            );
        }

        $models = [];

        foreach ($collection->find(array_values(array_unique($ids)), $context) as $model) {
            $modelContext = $context->forModel([$collection], $model);
            $models[$modelContext->id($modelContext->resource, $model)] = $model;
        }

        return $models;
    }
}
