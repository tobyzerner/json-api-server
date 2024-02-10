<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

trait IncludesData
{
    protected ?array $defaultInclude = null;

    public function defaultInclude(array $include): static
    {
        $this->defaultInclude = $include;

        return $this;
    }

    private function getInclude(Context $context): array
    {
        if (
            $includeString = $context->request->getQueryParams()['include'] ?? $this->defaultInclude
        ) {
            $include = $this->parseInclude($includeString);

            $this->validateInclude(
                $context,
                array_map(
                    fn($resource) => $context->resource($resource),
                    $context->collection->resources(),
                ),
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

                $types = $field->collections;

                $relatedResources = $types
                    ? array_map(fn($type) => $context->api->getResource($type), $types)
                    : array_values($context->api->resources);

                $this->validateInclude($context, $relatedResources, $nested, $name . '.');

                continue 2;
            }

            throw (new BadRequestException("Invalid include [$path$name]"))->setSource([
                'parameter' => 'include',
            ]);
        }
    }
}
