<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\JsonApi;

trait BuildsOpenApiPaths
{
    private function buildOpenApiContent(array $resources, bool $multiple = false): array
    {
        $item = count($resources) === 1 ? $resources[0] : ['oneOf' => $resources];

        return [
            JsonApi::MEDIA_TYPE => [
                'schema' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => [
                        'data' => $multiple ? ['type' => 'array', 'items' => $item] : $item,
                    ],
                ],
            ],
        ];
    }
}
