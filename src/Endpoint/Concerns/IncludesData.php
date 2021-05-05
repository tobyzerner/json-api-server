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

use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Relationship;
use function Tobyz\JsonApiServer\run_callbacks;

/**
 * @property JsonApi $api
 * @property ResourceType $resource
 */
trait IncludesData
{
    private function getInclude(Context $context): array
    {
        $queryParams = $context->getRequest()->getQueryParams();

        if (! empty($queryParams['include'])) {
            $include = $this->parseInclude($queryParams['include']);

            $this->validateInclude([$this->resource], $include);

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

    private function validateInclude(array $resources, array $include, string $path = '')
    {
        foreach ($include as $name => $nested) {
            foreach ($resources as $resource) {
                $fields = $resource->getSchema()->getFields();

                if (
                    ! isset($fields[$name])
                    || ! $fields[$name] instanceof Relationship
                    || ! $fields[$name]->isIncludable()
                ) {
                    continue;
                }

                $type = $fields[$name]->getType();

                if (is_string($type)) {
                    $relatedResource = $this->api->getResource($type);

                    $this->validateInclude([$relatedResource], $nested, $name.'.');
                } else {
                    $relatedResources = is_array($type) ? array_map(function ($type) {
                        return $this->api->getResource($type);
                    }, $type) : array_values($this->api->getResources());

                    $this->validateInclude($relatedResources, $nested, $name.'.');
                }

                continue 2;
            }

            throw new BadRequestException("Invalid include [{$path}{$name}]", 'include');
        }
    }

    private function loadRelationships(array $models, array $include, Context $context)
    {
        $this->loadRelationshipsAtLevel($models, [], $this->resource, $include, $context);
    }

    private function loadRelationshipsAtLevel(array $models, array $relationshipPath, ResourceType $resource, array $include, Context $context)
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

            if ($field->shouldLoad()) {
                $type = $field->getType();

                if (is_string($type)) {
                    $relatedResource = $this->api->getResource($type);
                    $scope = function ($query) use ($context, $field, $relatedResource) {
                        run_callbacks($relatedResource->getSchema()->getListeners('scope'), [$query, $context]);
                        run_callbacks($field->getListeners('scope'), [$query, $context]);
                    };
                } else {
                    $relatedResources = is_array($type) ? array_map(function ($type) {
                        return $this->api->getResource($type);
                    }, $type) : $this->api->getResources();

                    $scope = array_combine(
                        array_map(function ($relatedResource) {
                            return $relatedResource->getType();
                        }, $relatedResources),

                        array_map(function ($relatedResource) use ($context, $field) {
                            return [
                                'resource' => $relatedResource,
                                'scope' => function ($query) use ($context, $field, $relatedResource) {
                                    run_callbacks($relatedResource->getSchema()->getListeners('scope'), [$query, $context]);
                                    run_callbacks($field->getListeners('scope'), [$query, $context]);
                                }
                            ];
                        }, $relatedResources)
                    );
                }

                $adapter->load($models, $nextRelationshipPath, $scope, $field->hasLinkage());

                if (isset($include[$name]) && is_string($type)) {
                    $relatedResource = $this->api->getResource($type);

                    $this->loadRelationshipsAtLevel($models, $nextRelationshipPath, $relatedResource, $include[$name] ?? [], $context);
                }
            }
        }
    }
}
