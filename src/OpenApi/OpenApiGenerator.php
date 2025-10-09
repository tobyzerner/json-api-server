<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\SchemaContext;

class OpenApiGenerator
{
    public function generate(JsonApi $api): array
    {
        $jsonApiVersion = $api::VERSION;

        $document = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => '',
                'version' => '',
            ],
            'paths' => [],
            'components' => [
                'schemas' => [
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
                        'required' => ['type', 'id'],
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'id' => ['type' => 'string'],
                            'attributes' => ['type' => 'object'],
                            'relationships' => ['type' => 'object'],
                            'meta' => ['type' => 'object'],
                            'links' => ['type' => 'object'],
                        ],
                    ],
                    'jsonApiLinkObject' => [
                        'type' => 'object',
                        'required' => ['href'],
                        'properties' => [
                            'href' => ['type' => 'string', 'format' => 'uri'],
                            'rel' => ['type' => 'string'],
                            'describedby' => [
                                'oneOf' => [
                                    ['type' => 'string', 'format' => 'uri'],
                                    ['$ref' => '#/components/schemas/jsonApiLinkObject'],
                                ],
                            ],
                            'title' => ['type' => 'string'],
                            'type' => ['type' => 'string'],
                            'hreflang' => ['type' => 'string'],
                            'meta' => ['type' => 'object'],
                        ],
                    ],
                ],
            ],
            'externalDocs' => [
                'description' => "JSON:API v$jsonApiVersion Specification",
                'url' => "https://jsonapi.org/format/$jsonApiVersion/",
            ],
        ];

        if ($api->basePath) {
            $document['servers'] = [['url' => $api->basePath]];
        }

        $context = new SchemaContext($api);

        foreach ([...$api->collections, ...$api->resources] as $provider) {
            if ($provider instanceof ProvidesRootSchema) {
                $document = array_replace_recursive($document, $provider->rootSchema($context));
            }
        }

        return $document;
    }
}
