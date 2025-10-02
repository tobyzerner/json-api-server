<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class OpenApiTest extends AbstractTestCase
{
    public function test_generates_openapi_spec()
    {
        $api = new JsonApi();

        $api->resource(new MockResource('pets'));

        $api->resource(
            new MockResource(
                'users',
                endpoints: [Index::make()->description('list users'), Show::make(), Update::make()],
                fields: [
                    Attribute::make('name')->type(Type\Str::make()),
                    ToOne::make('pet')
                        ->nullable()
                        ->writable(),
                ],
                meta: [],
                filters: [],
                sorts: [],
            ),
        );

        $generator = new OpenApiGenerator();

        $definition = $generator->generate($api);

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
                    '/users/{id}' => [
                        'get' => [
                            'description' => 'Retrieve users resource',
                            'tags' => ['users'],
                            'parameters' => [
                                [
                                    'name' => 'id',
                                    'in' => 'path',
                                    'required' => true,
                                    'schema' => ['type' => 'string'],
                                ],
                            ],
                            'responses' => [
                                200 => [
                                    'content' => [
                                        'application/vnd.api+json' => [
                                            'schema' => [
                                                'type' => 'object',
                                                'required' => ['data'],
                                                'properties' => [
                                                    'data' => [
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
                    '/users/{id}/pet' => [
                        'get' => [
                            'description' => 'Retrieve related pet',
                            'tags' => ['users'],
                            'parameters' => [
                                [
                                    'name' => 'id',
                                    'in' => 'path',
                                    'required' => true,
                                    'schema' => ['type' => 'string'],
                                ],
                            ],
                            'responses' => [
                                200 => [
                                    'content' => [
                                        'application/vnd.api+json' => [
                                            'schema' => [
                                                'type' => 'object',
                                                'required' => ['data'],
                                                'properties' => [
                                                    'data' => [
                                                        'oneOf' => [
                                                            ['$ref' => '#/components/schemas/pets'],
                                                            ['type' => 'null'],
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
                    '/users/{id}/relationships/pet' => [
                        'get' => [
                            'description' => 'Retrieve pet relationship',
                            'tags' => ['users'],
                            'parameters' => [
                                [
                                    'name' => 'id',
                                    'in' => 'path',
                                    'required' => true,
                                    'schema' => ['type' => 'string'],
                                ],
                            ],
                            'responses' => [
                                200 => [
                                    'content' => [
                                        'application/vnd.api+json' => [
                                            'schema' => [
                                                '$ref' => '#/components/schemas/users_pet',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'patch' => [
                            'description' => 'Replace pet relationship',
                            'tags' => ['users'],
                            'parameters' => [
                                [
                                    'name' => 'id',
                                    'in' => 'path',
                                    'required' => true,
                                    'schema' => ['type' => 'string'],
                                ],
                            ],
                            'requestBody' => [
                                'required' => true,
                                'content' => [
                                    JsonApi::MEDIA_TYPE => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => [
                                                    '$ref' =>
                                                        '#/components/schemas/users_pet/properties/data',
                                                ],
                                            ],
                                            'required' => ['data'],
                                        ],
                                    ],
                                ],
                            ],
                            'responses' => [
                                200 => [
                                    'content' => [
                                        'application/vnd.api+json' => [
                                            'schema' => [
                                                '$ref' => '#/components/schemas/users_pet',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'patch' => [
                            'description' => 'Replace pet relationship',
                            'tags' => ['users'],
                            'parameters' => [
                                [
                                    'name' => 'id',
                                    'in' => 'path',
                                    'required' => true,
                                    'schema' => ['type' => 'string'],
                                ],
                            ],
                            'requestBody' => [
                                'required' => true,
                                'content' => [
                                    JsonApi::MEDIA_TYPE => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => [
                                                    '$ref' =>
                                                        '#/components/schemas/users_pet/properties/data',
                                                ],
                                            ],
                                            'required' => ['data'],
                                        ],
                                    ],
                                ],
                            ],
                            'responses' => [
                                200 => [
                                    'content' => [
                                        'application/vnd.api+json' => [
                                            'schema' => [
                                                '$ref' => '#/components/schemas/users_pet',
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
                                'attributes' => [
                                    'type' => 'object',
                                    'properties' => ['name' => ['type' => 'string']],
                                    'required' => ['name'],
                                ],
                                'relationships' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'pet' => ['$ref' => '#/components/schemas/users_pet'],
                                    ],
                                ],
                            ],
                        ],
                        'users_pet' => [
                            'type' => 'object',
                            'properties' => [
                                'data' => [
                                    'oneOf' => [
                                        [
                                            'allOf' => [
                                                [
                                                    '$ref' =>
                                                        '#/components/schemas/jsonApiResourceIdentifier',
                                                ],
                                                [
                                                    'properties' => [
                                                        'type' => [
                                                            'type' => 'string',
                                                            'enum' => ['pets'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        ['type' => 'null'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'externalDocs' => [
                    'description' => 'JSON:API v1.1 Specification',
                    'url' => 'https://jsonapi.org/format/1.1/',
                ],
            ],
            $definition,
        );
    }
}
