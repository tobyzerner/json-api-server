<?php

namespace Tobyz\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\ResourceType;

trait FindsResources
{
    private function findResource(Request $request, ResourceType $resource, $id)
    {
        $adapter = $resource->getAdapter();

        $query = $adapter->query();

        foreach ($resource->getSchema()->scopes as $scope) {
            $scope($request, $query, $id);
        }

        $model = $adapter->find($query, $id);

        if (! $model) {
            throw new ResourceNotFoundException($resource->getType(), $id);
        }

        return $model;
    }
}
