<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Resource\Resource;

use function Tobyz\JsonApiServer\location;

class OpenApiGenerator implements GeneratorInterface
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
        ];

        foreach ($api->collections as $collection) {
            foreach ($collection->endpoints() as $endpoint) {
                if ($endpoint instanceof OpenApiPathsProvider) {
                    $paths = array_merge_recursive($paths, $endpoint->getOpenApiPaths($collection));
                }
            }
        }

        foreach ($api->resources as $resource) {
            $schema = [];
            $createSchema = [];
            $updateSchema = [];

            foreach ($resource->fields() as $field) {
                $location = location($field);
                $fieldSchema = $field->getSchema($api);

                $schema[$location]['properties'][$field->name] = $fieldSchema;
                $schema[$location]['required'][] = $field->name;

                if ($field->writable) {
                    $updateSchema[$location]['properties'][$field->name] = $fieldSchema;
                    if ($field->required) {
                        $updateSchema[$location]['required'][] = $field->name;
                    }
                }

                if ($field->writableOnCreate) {
                    $createSchema[$location]['properties'][$field->name] = $fieldSchema;
                    if ($field->required) {
                        $createSchema[$location]['required'][] = $field->name;
                    }
                }
            }

            $type = $resource->type();

            $schemas[$type] = $this->buildSchema($resource, $schema, [
                'properties' => ['id' => ['type' => 'string', 'readOnly' => true]],
            ]);

            $schemas["{$type}Create"] = $this->buildSchema($resource, $createSchema, [
                'required' => ['type'],
            ]);

            $schemas["{$type}Update"] = $this->buildSchema($resource, $updateSchema);
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

    private function buildSchema(Resource $resource, array $schema, array $overrides = []): array
    {
        return array_replace_recursive(
            [
                'type' => 'object',
                'required' => ['type', 'id'],
                'properties' => [
                    'type' => ['type' => 'string', 'const' => $resource->type()],
                    'id' => ['type' => 'string'],
                    'attributes' => ['type' => 'object'] + ($schema['attributes'] ?? []),
                    'relationships' => ['type' => 'object'] + ($schema['relationships'] ?? []),
                ],
            ],
            $overrides,
        );
    }
}
