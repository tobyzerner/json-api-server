<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

trait BuildsOpenApiPaths
{
    private function buildOpenApiContent(array $resources, bool $multiple = false, bool $included = true): array
    {
        $item = count($resources) === 1 ? $resources[0] : ['oneOf' => $resources];

        return [
            JsonApi::MEDIA_TYPE => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => [
                        'data' => $multiple ? ['type' => 'array', 'items' => $item] : $item,
                        'included' => $included ? ['type' => 'array'] : [],
                    ],
                ],
            ],
        ];
    }

    private function buildOpenApiParameters(Resource $resource): array
    {
        return [
            ...$this->buildIncludeParameter($resource),
            ...$this->buildPaginationParameters($resource),
        ];
    }

    private function buildIncludeParameter(Resource $resource): array
    {
        $relationshipNames = array_map(
            fn(Relationship $relationship) => $relationship->name,
            array_filter(
                $resource->fields(),
                fn(Field $field) => $field instanceof Relationship && $field->includable,
            ),
        );

        if (empty($relationshipNames)) {
            return [];
        }

        $includes = implode(', ', $relationshipNames);

        return [
            [
                'name' => 'include',
                'in' => 'query',
                'description' => "Available include parameters: {$includes}.",
                'schema' => [
                    'type' => 'string',
                ],
            ],
        ];
    }

    private function buildPaginationParameters(Resource $resource): array
    {
        return [
            [
                'name' => 'page[limit]',
                'in' => 'query',
                'description' => "The limit pagination field.",
                'schema' => [
                    'type' => 'number',
                ],
            ],
            [
                'name' => 'page[offset]',
                'in' => 'query',
                'description' => "The offset pagination field.",
                'schema' => [
                    'type' => 'number',
                ],
            ],
        ];
    }
}
