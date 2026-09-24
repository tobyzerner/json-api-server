<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Request\InvalidIncludeException;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

trait IncludesData
{
    protected ?array $defaultInclude = null;

    public function defaultInclude(array $include): static
    {
        $this->defaultInclude = $include;

        return $this;
    }

    private function getInclude(Context $context, ?array $collections = null): array
    {
        if ($includeString = $context->parameter('include')) {
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
            $relatedResources = null;

            foreach ($resources as $resource) {
                $fields = $context->fields($resource);

                if (
                    !($field = $fields[$name] ?? null) ||
                    !$field instanceof Relationship ||
                    !$field->includable
                ) {
                    continue;
                }

                $relatedResources ??= [];
                $relatedResources += $this->getRelatedResources(
                    array_map($context->api->getCollection(...), $field->collections),
                    $context,
                );
            }

            if ($relatedResources === null) {
                throw (new InvalidIncludeException($path . $name))->source(['parameter' => 'include']);
            }

            $this->validateInclude($context, $relatedResources, $nested, $path . $name . '.');
        }
    }

    private function getRelatedResources(array $collections, Context $context): array
    {
        $resources = [];

        foreach ($collections as $collection) {
            foreach ($collection->resources() as $type) {
                $resources[$type] = $context->api->getResource($type);
            }
        }

        return $resources;
    }
}
