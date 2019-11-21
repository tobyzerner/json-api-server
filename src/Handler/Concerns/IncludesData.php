<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Relationship;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\run_callbacks;

/**
 * @property JsonApi $api
 * @property ResourceType $resource
 */
trait IncludesData
{
    private function getInclude(Request $request): array
    {
        $queryParams = $request->getQueryParams();

        if (! empty($queryParams['include'])) {
            $include = $this->parseInclude($queryParams['include']);

            $this->validateInclude($this->resource, $include);

            return $include;
        }

        return [];
    }

    private function parseInclude(string $include): array
    {
        $tree = [];

        foreach (explode(',', $include) as $path) {
            $array = &$tree;

            foreach (explode('.', $path) as $key) {
                if (! isset($array[$key])) {
                    $array[$key] = [];
                }

                $array = &$array[$key];
            }
        }

        return $tree;
    }

    private function validateInclude(ResourceType $resource, array $include, string $path = '')
    {
        $fields = $resource->getSchema()->getFields();

        foreach ($include as $name => $nested) {
            if (
                ! isset($fields[$name])
                || ! $fields[$name] instanceof Relationship
                || ! $fields[$name]->isIncludable()
            ) {
                throw new BadRequestException("Invalid include [{$path}{$name}]", 'include');
            }

            if ($type = $fields[$name]->getType()) {
                $relatedResource = $this->api->getResource($type);

                $this->validateInclude($relatedResource, $nested, $name.'.');
            } elseif ($nested) {
                throw new BadRequestException("Invalid include [{$path}{$name}.*]", 'include');
            }
        }
    }

    private function loadRelationships(array $models, array $include, Request $request)
    {
        $this->loadRelationshipsAtLevel($models, [], $this->resource, $include, $request);
    }

    private function loadRelationshipsAtLevel(array $models, array $relationshipPath, ResourceType $resource, array $include, Request $request)
    {
        $adapter = $resource->getAdapter();
        $fields = $resource->getSchema()->getFields();

        foreach ($fields as $name => $field) {
            if (
                ! $field instanceof Relationship
                || (! $field->isLinkage() && ! isset($include[$name]))
                || $field->isVisible() === false
            ) {
                continue;
            }

            $nextRelationshipPath = array_merge($relationshipPath, [$field]);

            if ($load = $field->isLoadable()) {
                if (is_callable($load)) {
                    $load($models, $nextRelationshipPath, $field->isLinkage(), $request);
                } else {
                    $scope = function ($query) use ($request, $field) {
                        run_callbacks($field->getListeners('scope'), [$query, $request]);
                    };

                    $adapter->load($models, $nextRelationshipPath, $scope, $field->isLinkage());
                }

                if (isset($include[$name]) && is_string($type = $field->getType())) {
                    $relatedResource = $this->api->getResource($type);

                    $this->loadRelationshipsAtLevel($models, $nextRelationshipPath, $relatedResource, $include[$name] ?? [], $request);
                }
            }
        }
    }
}
