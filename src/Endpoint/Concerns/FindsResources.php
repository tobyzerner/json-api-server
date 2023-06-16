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
        $resource = $context->resource;

        if (!$resource instanceof Findable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($resource), Findable::class),
            );
        }

        if (!($model = $resource->find($id, $context))) {
            throw new ResourceNotFoundException($resource->type(), $id);
        }

        return $model;
    }
}
