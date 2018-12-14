<?php

namespace Tobscure\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tobscure\JsonApiServer\Exception\ResourceNotFoundException;
use Tobscure\JsonApiServer\ResourceType;

trait FindsResources
{
    private function findResource(Request $request, ResourceType $resource, $id)
    {
        $adapter = $resource->getAdapter();

        $query = $adapter->query();

        foreach ($resource->getSchema()->scopes as $scope) {
            $scope($query, $request);
        }

        $model = $adapter->find($query, $id);

        if (! $model) {
            throw new ResourceNotFoundException($resource->getType(), $id);
        }

        return $model;
    }
}
