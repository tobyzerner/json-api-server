<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

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
        ];

        foreach ($api->collections as $collection) {
            foreach ($collection->endpoints() as $endpoint) {
                if ($endpoint instanceof OpenApiPathsProvider) {
                    $paths = array_merge_recursive(
                        $paths,
                        $endpoint->getOpenApiPaths($collection, $api),
                    );
                }
            }
        }

        foreach ($api->resources as $resource) {
            $type = $resource->type();
            $id = $resource->id();

            $schema = [
                'type' => 'object',
                'required' => ['type'],
                'properties' => [
                    'type' => ['type' => 'string', 'const' => $type],
                    'attributes' => ['type' => 'object'],
                    'relationships' => ['type' => 'object'],
                ],
            ];

            $createSchema = $schema;

            $updateSchema = array_merge_recursive($schema, [
                'required' => ['id'],
                'properties' => ['id' => $id->getSchema($api)],
            ]);

            foreach ([$id, ...$resource->fields()] as $field) {
                $location = $field::location();
                $valueSchema = $field->getSchema($api);

                if ($field instanceof Relationship) {
                    $relationshipSchema = "{$type}_{$field->name}";
                    $schemas[$relationshipSchema] = $valueSchema;
                    $valueSchema = ['$ref' => "#/components/schemas/$relationshipSchema"];
                }

                if ($location) {
                    $fieldSchema = &$schema['properties'][$location];
                    $fieldUpdateSchema = &$updateSchema['properties'][$location];
                    $fieldCreateSchema = &$createSchema['properties'][$location];
                } else {
                    $fieldSchema = &$schema;
                    $fieldUpdateSchema = &$updateSchema;
                    $fieldCreateSchema = &$createSchema;
                }

                $fieldSchema['properties'][$field->name] = $valueSchema;
                $fieldSchema['required'][] = $field->name;

                if ($field->writable) {
                    $fieldUpdateSchema['properties'][$field->name] = $valueSchema;
                }

                if ($field->writableOnCreate) {
                    $fieldCreateSchema['properties'][$field->name] = $valueSchema;
                    if ($field->required) {
                        $fieldCreateSchema['required'][] = $field->name;
                    }
                }
            }

            $schemas[$type] = $schema;
            $schemas["{$type}_create"] = $createSchema;
            $schemas["{$type}_update"] = $updateSchema;
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
