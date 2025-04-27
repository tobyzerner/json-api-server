<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class OpenApiTest extends AbstractTestCase
{
    public function test_generates_openapi_spec()
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                endpoints: [Index::make()->description('list users')],
                fields: [],
                meta: [],
                filters: [],
                sorts: [],
            ),
        );

        $generator = new OpenApiGenerator();

        $this->assertArraySubset(
            [
                'openapi' => '3.1.0',
                'paths' => [
                    '/users' => [
                        'get' => [
                            'description' => 'list users',
                            'tags' => ['users'],
                            'responses' => [
                                200 => [
                                    'content' => [
                                        'application/vnd.api+json' => [
                                            'schema' => [
                                                'type' => 'object',
                                                'required' => ['data'],
                                                'properties' => [
                                                    'data' => [
                                                        'type' => 'array',
                                                        'items' => [
                                                            '$ref' => '#/components/schemas/users',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
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
                        'users' => [
                            'type' => 'object',
                            'required' => ['type', 'id'],
                            'properties' => [
                                'type' => ['type' => 'string', 'const' => 'users'],
                                'id' => ['type' => 'string', 'readOnly' => true],
                                'attributes' => ['type' => 'object'],
                                'relationships' => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
                'externalDocs' => [
                    'description' => 'JSON:API v1.1 Specification',
                    'url' => 'https://jsonapi.org/format/1.1/',
                ],
            ],
            $generator->generate($api),
        );
    }
}
