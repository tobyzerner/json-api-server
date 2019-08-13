<?php

namespace Tobscure\JsonApiServer\Handler\Concerns;

use Psr\Http\Message\ServerRequestInterface as Request;
use Tobscure\JsonApiServer\Exception\BadRequestException;
use Tobscure\JsonApiServer\ResourceType;
use Tobscure\JsonApiServer\Schema\HasMany;
use Tobscure\JsonApiServer\Schema\Relationship;

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
            $keys = explode('.', $path);
            $array = &$tree;

            foreach ($keys as $key) {
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
        $schema = $resource->getSchema();

        foreach ($include as $name => $nested) {
            if (! isset($schema->fields[$name])
                || ! $schema->fields[$name] instanceof Relationship
                || ($schema->fields[$name] instanceof HasMany && ! $schema->fields[$name]->includable)
            ) {
                throw new BadRequestException("Invalid include [{$path}{$name}]", 'include');
            }

            if (is_string($schema->fields[$name]->resource)) {
                $relatedResource = $this->api->getResource($schema->fields[$name]->resource);

                $this->validateInclude($relatedResource, $nested, $name.'.');
            }
        }
    }

    private function buildRelationshipTrails(ResourceType $resource, array $include): array
    {
        $schema = $resource->getSchema();
        $trails = [];

        foreach ($include as $name => $nested) {
            $relationship = $schema->fields[$name];

            if ($relationship->loadable) {
                $trails[] = [$relationship];
            }

            if (is_string($schema->fields[$name]->resource)) {
                $relatedResource = $this->api->getResource($relationship->resource);

                $trails = array_merge(
                    $trails,
                    array_map(
                        function ($trail) use ($relationship) {
                            return array_merge([$relationship], $trail);
                        },
                        $this->buildRelationshipTrails($relatedResource, $nested)
                    )
                );
            }
        }

        return $trails;
    }

    private function loadRelationships(array $models, array $include, Request $request)
    {
        $adapter = $this->resource->getAdapter();
        $schema = $this->resource->getSchema();

        foreach ($schema->fields as $name => $field) {
            if (! $field instanceof Relationship || ! ($field->linkage)($request) || ! $field->loadable) {
                continue;
            }

            if ($field->loader) {
                ($field->loader)($models, true);
            } else {
                $adapter->loadIds($models, $field);
            }
        }

        $trails = $this->buildRelationshipTrails($this->resource, $include);

        foreach ($trails as $relationships) {
            if ($loader = end($relationships)->loader) {
                // TODO: probably need to loop through relationships here
                ($loader)($models, false);
            } else {
                $scope = function ($query) use ($relationships, $request) {
                    foreach ($this->api->getResource(end($relationships)->resource)->getSchema()->scopes as $scope) {
                        $scope($request, $query);
                    }
                };

                $adapter->load($models, $relationships, $scope);
            }
        }
    }
}
