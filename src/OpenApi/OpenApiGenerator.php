<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\JsonApi;

use function Tobyz\JsonApiServer\location;

class OpenApiGenerator
{
    public function generate(JsonApi $api): array
    {
        $jsonApiVersion = $api::VERSION;
        $paths = [];
        $schemas = [
            'jsonApiResourceIdentifier' => [
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string'],
                ],
            ],
            'jsonApiResource' => [
                'type' => 'object',
                'discriminator' => ['propertyName' => 'type'],
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string'],
                    'id' => ['type' => 'string'],
                    'attributes' => ['type' => 'object'],
                    'relationships' => ['type' => 'object'],
                    'links' => ['type' => 'object', 'readOnly' => true],
                ],
            ],
        ];

        foreach ($api->collections as $collection) {
            foreach ($collection->endpoints() as $endpoint) {
                if ($endpoint instanceof OpenApiPathsProvider) {
                    $paths = array_merge_recursive($paths, $endpoint->getOpenApiPaths($collection));
                }
            }
        }

        foreach ($api->resources as $resource) {
            $schema = ['attributes' => [], 'relationships' => []];
            $writableSchema = ['attributes' => [], 'relationships' => []];

            foreach ($resource->fields() as $field) {
                $schema[location($field)]['properties'][$field->name] = $field->getSchema($api);
                $schema[location($field)]['required'][] = $field->name;

                if ($field->writable) {
                    $writableSchema[location($field)]['properties'][
                        $field->name
                    ] = $field->getSchema($api);

                    if ($field->required) {
                        $writableSchema[location($field)]['required'][] = $field->name;
                    }
                }
            }

            $schemas[$resource->type()] = [
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string', 'const' => $resource->type()],
                    'id' => ['type' => 'string', 'readOnly' => true],
                    ...$schema['attributes']
                        ? ['attributes' => ['type' => 'object'] + $schema['attributes']]
                        : [],
                    ...$schema['relationships']
                        ? ['relationships' => ['type' => 'object'] + $schema['relationships']]
                        : [],
                ],
            ];

            $schemas["{$resource->type()}Create"] = [
                'type' => 'object',
                'required' => ['type'],
                'properties' => [
                    'type' => ['type' => 'string', 'const' => $resource->type()],
                    'id' => ['type' => 'string'],
                    ...$writableSchema['attributes']
                        ? ['attributes' => ['type' => 'object'] + $writableSchema['attributes']]
                        : [],
                    ...$writableSchema['relationships']
                        ? [
                            'relationships' =>
                                ['type' => 'object'] + $writableSchema['relationships'],
                        ]
                        : [],
                ],
            ];

            $schemas["{$resource->type()}Update"] = [
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string', 'const' => $resource->type()],
                    'id' => ['type' => 'string'],
                    ...$writableSchema['attributes']
                        ? ['attributes' => ['type' => 'object'] + $writableSchema['attributes']]
                        : [],
                    ...$writableSchema['relationships']
                        ? [
                            'relationships' =>
                                ['type' => 'object'] + $writableSchema['relationships'],
                        ]
                        : [],
                ],
            ];
        }

        return array_filter([
            'openapi' => '3.1.0',
            'servers' => $api->basePath ? [['url' => $api->basePath]] : null,
            'paths' => $paths,
            'components' => [
                'schemas' => $schemas,
            ],
            'externalDocs' => [
                'description' => "JSON:API v$jsonApiVersion Specification",
                'url' => "https://jsonapi.org/format/$jsonApiVersion/",
            ],
        ]);
    }
}
