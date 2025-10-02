<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Id;
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
                id: Id::make()
                    ->writableOnCreate()
                    ->required()
                    ->type(
                        Type\Str::make()
                            ->pattern('^[a-z0-9-]+$')
                            ->minLength(3),
                    ),
                fields: [
                    Attribute::make('name')->type(Type\Str::make()),
                    ToOne::make('pet')
                        ->nullable()
                        ->writable(),
                ],
            ),
        );

        $generator = new OpenApiGenerator();

        $definition = $generator->generate($api);

        $this->assertEquals(
            json_decode(
                json_encode([
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
                                                                '$ref' =>
                                                                    '#/components/schemas/users',
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
                                        'schema' => [
                                            'type' => 'string',
                                        ],
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
                            'patch' => [
                                'description' => 'Update users resource',
                                'tags' => ['users'],
                                'parameters' => [
                                    [
                                        'name' => 'id',
                                        'in' => 'path',
                                        'required' => true,
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                'requestBody' => [
                                    'required' => true,
                                    'content' => [
                                        'application/vnd.api+json' => [
                                            'schema' => [
                                                'type' => 'object',
                                                'required' => ['data'],
                                                'properties' => [
                                                    'data' => [
                                                        '$ref' =>
                                                            '#/components/schemas/users_update',
                                                    ],
                                                ],
                                            ],
                                        ],
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
                                        'schema' => [
                                            'type' => 'string',
                                        ],
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
                                                                [
                                                                    '$ref' =>
                                                                        '#/components/schemas/pets',
                                                                ],
                                                                [
                                                                    'type' => 'null',
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
                        '/users/{id}/relationships/pet' => [
                            'get' => [
                                'description' => 'Retrieve pet relationship',
                                'tags' => ['users'],
                                'parameters' => [
                                    [
                                        'name' => 'id',
                                        'in' => 'path',
                                        'required' => true,
                                        'schema' => [
                                            'type' => 'string',
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
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                                'requestBody' => [
                                    'required' => true,
                                    'content' => [
                                        'application/vnd.api+json' => [
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
                            'pets' => [
                                'type' => 'object',
                                'required' => ['type', 'id'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'const' => 'pets',
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                    ],
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                    ],
                                ],
                            ],
                            'pets_create' => [
                                'type' => 'object',
                                'required' => ['type'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'const' => 'pets',
                                    ],
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                    ],
                                ],
                            ],
                            'pets_update' => [
                                'type' => 'object',
                                'required' => ['type', 'id'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'const' => 'pets',
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                    ],
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
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
                            'users' => [
                                'type' => 'object',
                                'required' => ['type', 'id'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'const' => 'users',
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'pattern' => '^[a-z0-9-]+$',
                                    ],
                                    'attributes' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                        ],
                                        'required' => ['name'],
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'pet' => ['$ref' => '#/components/schemas/users_pet'],
                                        ],
                                        'required' => ['pet'],
                                    ],
                                ],
                            ],
                            'users_create' => [
                                'type' => 'object',
                                'required' => ['type', 'id'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'const' => 'users',
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'pattern' => '^[a-z0-9-]+$',
                                    ],
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                    ],
                                ],
                            ],
                            'users_update' => [
                                'type' => 'object',
                                'required' => ['type', 'id'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
                                        'const' => 'users',
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'pattern' => '^[a-z0-9-]+$',
                                    ],
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'pet' => [
                                                '$ref' => '#/components/schemas/users_pet',
                                            ],
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
                ]),
                true,
            ),
            json_decode(json_encode($definition), true),
        );
    }
}
