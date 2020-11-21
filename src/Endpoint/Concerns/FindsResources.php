<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\ResourceType;
use function Tobyz\JsonApiServer\run_callbacks;

trait FindsResources
{
    /**
     * Find a resource within the API after applying scopes for the resource type.
     *
     * @throws ResourceNotFoundException if the resource is not found.
     */
    private function findResource(ResourceType $resource, string $id, ServerRequestInterface $request)
    {
        $adapter = $resource->getAdapter();
        $query = $adapter->newQuery();

        run_callbacks($resource->getSchema()->getListeners('scope'), [$query, $request]);

        $model = $adapter->find($query, $id);

        if (! $model) {
            throw new ResourceNotFoundException($resource->getType(), $id);
        }

        return $model;
    }
}
