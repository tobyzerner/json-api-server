<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Request\InvalidIncludeException;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

trait IncludesData
{
    protected ?array $defaultInclude = null;

    public function defaultInclude(array $include): static
    {
        $this->defaultInclude = $include;

        return $this;
    }

    private function getInclude(Context $context, array $collections = null): array
    {
        if (
            $includeString = $context->request->getQueryParams()['include'] ?? $this->defaultInclude
        ) {
            $include = $this->parseInclude($includeString);

            $this->validateInclude(
                $context,
                $this->getRelatedResources($collections ?? [$context->collection], $context),
                $include,
            );

            return $include;
        }

        return [];
    }

    private function parseInclude($include): array
    {
        $tree = [];

        foreach (is_array($include) ? $include : explode(',', $include) as $path) {
            $array = &$tree;

            foreach (explode('.', $path) as $key) {
                if (!isset($array[$key])) {
                    $array[$key] = [];
                }

                $array = &$array[$key];
            }
        }

        return $tree;
    }

    private function validateInclude(
        Context $context,
        array $resources,
        array $include,
        string $path = '',
    ): void {
        foreach ($include as $name => $nested) {
            foreach ($resources as $resource) {
                $fields = $context->fields($resource);

                if (
                    !($field = $fields[$name] ?? null) ||
                    !$field instanceof Relationship ||
                    !$field->includable
                ) {
                    continue;
                }

                $relatedResources = $this->getRelatedResources(
                    array_map(
                        fn($collection) => $context->api->getCollection($collection),
                        $field->collections,
                    ),
                    $context,
                );

                $this->validateInclude($context, $relatedResources, $nested, $name . '.');

                continue 2;
            }

            throw (new InvalidIncludeException($path . $name))->source(['parameter' => 'include']);
        }
    }

    private function getRelatedResources(array $collections, Context $context): array
    {
        return array_merge(
            ...array_map(
                fn(Collection $collection) => array_map(
                    fn($resource) => $context->api->getResource($resource),
                    $collection->resources(),
                ),
                $collections,
            ),
        );
    }
}
