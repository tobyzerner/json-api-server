<?php

namespace Tobyz\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\ResourceType;
use function Tobyz\JsonApiServer\run_callbacks;

trait FindsResources
{
    private function findResource(Request $request, ResourceType $resource, string $id)
    {
        $adapter = $resource->getAdapter();

        $query = $adapter->query();

        run_callbacks($resource->getSchema()->getScopes(), [$query, $request, $id]);

        $model = $adapter->find($query, $id);

        if (! $model) {
            throw new ResourceNotFoundException($resource->getType(), $id);
        }

        return $model;
    }
}
