<?php

namespace Tobyz\JsonApiServer\Handler\Concerns;

use Closure;
use Psr\Http\Message\ServerRequestInterface as Request;
use function Tobyz\JsonApiServer\evaluate;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Relationship;

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
        $fields = $resource->getSchema()->getFields();

        foreach ($include as $name => $nested) {
            if (! isset($fields[$name])
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

    private function buildRelationshipTrails(ResourceType $resource, array $include): array
    {
        $fields = $resource->getSchema()->getFields();
        $trails = [];

        foreach ($include as $name => $nested) {
            $relationship = $fields[$name];

            if ($relationship->getLoadable()) {
                $trails[] = [$relationship];
            }

            if ($type = $fields[$name]->getType()) {
                $relatedResource = $this->api->getResource($type);

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
        $fields = $this->resource->getSchema()->getFields();

        // TODO: don't load IDs for relationships which are included below
        foreach ($fields as $name => $field) {
            if (! $field instanceof Relationship || ! evaluate($field->getLinkage(), [$request]) || ! $field->getLoadable()) {
                continue;
            }

            if (($load = $field->getLoadable()) instanceof Closure) {
                $load($models, true);
            } else {
                $adapter->loadIds($models, $field);
            }
        }

        $trails = $this->buildRelationshipTrails($this->resource, $include);

        foreach ($trails as $relationships) {
            if (($load = end($relationships)->getLoadable()) instanceof Closure) {
                // TODO: probably need to loop through relationships here
                $load($models, false);
            } else {
                $adapter->load($models, $relationships);
            }
        }
    }
}
