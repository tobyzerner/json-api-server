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
        $collection = $context->collection;

        if (!$collection instanceof Findable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Findable::class),
            );
        }

        if (!($model = $collection->find($id, $context))) {
            throw new ResourceNotFoundException($collection->name(), $id);
        }

        return $model;
    }
}
