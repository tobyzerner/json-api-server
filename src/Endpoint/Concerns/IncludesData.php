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
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Relationship;
use function Tobyz\JsonApiServer\run_callbacks;

/**
 * @property JsonApi $api
 * @property ResourceType $resource
 */
trait IncludesData
{
    private function getInclude(ServerRequestInterface $request): array
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

            if (($type = $fields[$name]->getType()) && is_string($type)) {
                $relatedResource = $this->api->getResource($type);

                $this->validateInclude($relatedResource, $nested, $name.'.');
            } elseif ($nested) {
                throw new BadRequestException("Invalid include [{$path}{$name}.*]", 'include');
            }
        }
    }

    private function loadRelationships(array $models, array $include, ServerRequestInterface $request)
    {
        $this->loadRelationshipsAtLevel($models, [], $this->resource, $include, $request);
    }

    private function loadRelationshipsAtLevel(array $models, array $relationshipPath, ResourceType $resource, array $include, ServerRequestInterface $request)
    {
        $adapter = $resource->getAdapter();
        $schema = $resource->getSchema();
        $fields = $schema->getFields();

        foreach ($fields as $name => $field) {
            if (
                ! $field instanceof Relationship
                || (! $field->hasLinkage() && ! isset($include[$name]))
                || $field->getVisible() === false
            ) {
                continue;
            }

            $nextRelationshipPath = array_merge($relationshipPath, [$field]);

            if ($load = $field->getLoad()) {
                $type = $field->getType();

                if (is_callable($load)) {
                    $load($models, $nextRelationshipPath, $field->hasLinkage(), $request);
                } else {
                    if (is_string($type)) {
                        $relatedResource = $this->api->getResource($type);
                        $scope = function ($query) use ($request, $field, $relatedResource) {
                            run_callbacks($relatedResource->getSchema()->getListeners('scope'), [$query, $request]);
                            run_callbacks($field->getListeners('scope'), [$query, $request]);
                        };
                    } else {
                        $relatedResources = is_array($type) ? array_map(function ($type) {
                            return $this->api->getResource($type);
                        }, $type) : $this->api->getResources();

                        $scope = array_combine(
                            array_map(function ($relatedResource) {
                                return $relatedResource->getType();
                            }, $relatedResources),

                            array_map(function ($relatedResource) use ($request, $field) {
                                return function ($query) use ($request, $field, $relatedResource) {
                                    run_callbacks($relatedResource->getSchema()->getListeners('scope'), [$query, $request]);
                                    run_callbacks($field->getListeners('scope'), [$query, $request]);
                                };
                            }, $relatedResources)
                        );
                    }

                    $adapter->load($models, $nextRelationshipPath, $scope, $field->hasLinkage());
                }

                if (isset($include[$name]) && is_string($type)) {
                    $relatedResource = $this->api->getResource($type);

                    $this->loadRelationshipsAtLevel($models, $nextRelationshipPath, $relatedResource, $include[$name] ?? [], $request);
                }
            }
        }
    }
}
