<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
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

        $api->resource(new MockResource('pets', pagination: new OffsetPagination()));

        $api->resource(
            new MockResource(
                'users',
                endpoints: [
                    Index::make()->description('list users'),
                    Show::make(),
                    Create::make(),
                    Update::make(),
                    Delete::make(),
                ],

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
                    ToMany::make('pets')->includable(),
                ],
            ),
        );

        $definition = (new OpenApiGenerator())->generate($api);

        $this->assertEquals(
            json_decode(
                json_encode([
                    'openapi' => '3.1.0',
                    'info' => [
                        'title' => '',
                        'version' => '',
                    ],
                    'paths' => [
                        '/users' => [
                            'get' => [
                                'tags' => ['users'],
                                'parameters' => [
                                    [
                                        'name' => 'include',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                        'description' =>
                                            'Comma-separated list of relationship paths to include',
                                    ],
                                    [
                                        'name' => 'fields',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'description' =>
                                            'Comma-separated sparse fieldsets keyed by type',
                                    ],
                                ],
                                'responses' => [
                                    '200' => [
                                        'description' => 'Successful list response.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/users',
                                                            ],
                                                        ],
                                                        'included' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/jsonApiResource',
                                                            ],
                                                        ],
                                                    ],
                                                    'required' => ['data'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'description' => 'list users',
                            ],
                            'post' => [
                                'tags' => ['users'],
                                'parameters' => [
                                    [
                                        'name' => 'include',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                        'description' =>
                                            'Comma-separated list of relationship paths to include',
                                    ],
                                    [
                                        'name' => 'fields',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'description' =>
                                            'Comma-separated sparse fieldsets keyed by type',
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
                                                            '#/components/schemas/users_create',
                                                    ],
                                                    'included' => [
                                                        'type' => 'array',
                                                        'items' => [
                                                            '$ref' =>
                                                                '#/components/schemas/jsonApiResource',
                                                        ],
                                                    ],
                                                ],
                                                'required' => ['data'],
                                            ],
                                        ],
                                    ],
                                ],
                                'responses' => [
                                    '201' => [
                                        'description' => 'Resource created successfully.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            '$ref' => '#/components/schemas/users',
                                                        ],
                                                        'included' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/jsonApiResource',
                                                            ],
                                                        ],
                                                    ],
                                                    'required' => ['data'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '/users/{id}' => [
                            'get' => [
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
                                    [
                                        'name' => 'include',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                        'description' =>
                                            'Comma-separated list of relationship paths to include',
                                    ],
                                    [
                                        'name' => 'fields',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'description' =>
                                            'Comma-separated sparse fieldsets keyed by type',
                                    ],
                                ],
                                'responses' => [
                                    '200' => [
                                        'description' => 'Successful show response.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            '$ref' => '#/components/schemas/users',
                                                        ],
                                                        'included' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/jsonApiResource',
                                                            ],
                                                        ],
                                                    ],
                                                    'required' => ['data'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'patch' => [
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
                                    [
                                        'name' => 'include',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                        'description' =>
                                            'Comma-separated list of relationship paths to include',
                                    ],
                                    [
                                        'name' => 'fields',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'description' =>
                                            'Comma-separated sparse fieldsets keyed by type',
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
                                                            '#/components/schemas/users_update',
                                                    ],
                                                    'included' => [
                                                        'type' => 'array',
                                                        'items' => [
                                                            '$ref' =>
                                                                '#/components/schemas/jsonApiResource',
                                                        ],
                                                    ],
                                                ],
                                                'required' => ['data'],
                                            ],
                                        ],
                                    ],
                                ],
                                'responses' => [
                                    '200' => [
                                        'description' => 'Resource updated successfully.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            '$ref' => '#/components/schemas/users',
                                                        ],
                                                        'included' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/jsonApiResource',
                                                            ],
                                                        ],
                                                    ],
                                                    'required' => ['data'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'delete' => [
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
                                    '204' => [
                                        'description' => 'Resource deleted successfully.',
                                    ],
                                ],
                            ],
                        ],
                        '/users/{id}/pet' => [
                            'get' => [
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
                                    [
                                        'name' => 'include',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                        'description' =>
                                            'Comma-separated list of relationship paths to include',
                                    ],
                                    [
                                        'name' => 'fields',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'description' =>
                                            'Comma-separated sparse fieldsets keyed by type',
                                    ],
                                ],
                                'responses' => [
                                    '200' => [
                                        'description' => 'Successful show related response.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
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
                                                        'included' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/jsonApiResource',
                                                            ],
                                                        ],
                                                    ],
                                                    'required' => ['data'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '/users/{id}/pets' => [
                            'get' => [
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
                                    [
                                        'name' => 'include',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'string',
                                        ],
                                        'description' =>
                                            'Comma-separated list of relationship paths to include',
                                    ],
                                    [
                                        'name' => 'fields',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'object',
                                            'additionalProperties' => [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'description' =>
                                            'Comma-separated sparse fieldsets keyed by type',
                                    ],
                                    [
                                        'name' => 'page[offset]',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'integer',
                                            'minimum' => 0.0,
                                        ],
                                    ],
                                    [
                                        'name' => 'page[limit]',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'integer',
                                            'minimum' => 1.0,
                                            'maximum' => 50.0,
                                        ],
                                    ],
                                ],
                                'responses' => [
                                    '200' => [
                                        'description' => 'Successful show related response.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'meta' => [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'page' => [
                                                                    'type' => 'object',
                                                                    'properties' => [
                                                                        'total' => [
                                                                            'type' => 'integer',
                                                                        ],
                                                                    ],
                                                                ],
                                                            ],
                                                        ],
                                                        'links' => [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'first' => [
                                                                    'type' => 'string',
                                                                    'format' => 'uri',
                                                                ],
                                                                'prev' => [
                                                                    'type' => 'string',
                                                                    'format' => 'uri',
                                                                ],
                                                                'next' => [
                                                                    'type' => 'string',
                                                                    'format' => 'uri',
                                                                ],
                                                                'last' => [
                                                                    'type' => 'string',
                                                                    'format' => 'uri',
                                                                ],
                                                            ],
                                                        ],
                                                        'data' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/pets',
                                                            ],
                                                        ],
                                                        'included' => [
                                                            'type' => 'array',
                                                            'items' => [
                                                                '$ref' =>
                                                                    '#/components/schemas/jsonApiResource',
                                                            ],
                                                        ],
                                                    ],
                                                    'required' => ['data'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '/users/{id}/relationships/pet' => [
                            'get' => [
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
                                    '200' => [
                                        'description' => 'Successful show relationship response.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            '$ref' =>
                                                                '#/components/schemas/users_relationship_pet',
                                                        ],
                                                    ],
                                                    'required' => ['data'],
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
                                                            '#/components/schemas/users_relationship_pet',
                                                    ],
                                                ],
                                                'required' => ['data'],
                                            ],
                                        ],
                                    ],
                                ],
                                'responses' => [
                                    '200' => [
                                        'description' => 'Relationship updated successfully.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            '$ref' =>
                                                                '#/components/schemas/users_relationship_pet',
                                                        ],
                                                    ],
                                                    'required' => ['data'],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '/users/{id}/relationships/pets' => [
                            'get' => [
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
                                    [
                                        'name' => 'page[offset]',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'integer',
                                            'minimum' => 0.0,
                                        ],
                                    ],
                                    [
                                        'name' => 'page[limit]',
                                        'in' => 'query',
                                        'schema' => [
                                            'type' => 'integer',
                                            'minimum' => 1.0,
                                            'maximum' => 50.0,
                                        ],
                                    ],
                                ],
                                'responses' => [
                                    '200' => [
                                        'description' => 'Successful show relationship response.',
                                        'content' => [
                                            'application/vnd.api+json' => [
                                                'schema' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'data' => [
                                                            '$ref' =>
                                                                '#/components/schemas/users_relationship_pets',
                                                        ],
                                                    ],
                                                    'required' => ['data'],
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
                                    'type' => [
                                        'type' => 'string',
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                            'jsonApiResource' => [
                                'type' => 'object',
                                'required' => ['type', 'id'],
                                'properties' => [
                                    'type' => [
                                        'type' => 'string',
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
                                    'meta' => [
                                        'type' => 'object',
                                    ],
                                    'links' => [
                                        'type' => 'object',
                                    ],
                                ],
                            ],
                            'jsonApiLinkObject' => [
                                'type' => 'object',
                                'required' => ['href'],
                                'properties' => [
                                    'href' => [
                                        'type' => 'string',
                                        'format' => 'uri',
                                    ],
                                    'rel' => [
                                        'type' => 'string',
                                    ],
                                    'describedby' => [
                                        'oneOf' => [
                                            [
                                                'type' => 'string',
                                                'format' => 'uri',
                                            ],
                                            [
                                                '$ref' => '#/components/schemas/jsonApiLinkObject',
                                            ],
                                        ],
                                    ],
                                    'title' => [
                                        'type' => 'string',
                                    ],
                                    'type' => [
                                        'type' => 'string',
                                    ],
                                    'hreflang' => [
                                        'type' => 'string',
                                    ],
                                    'meta' => [
                                        'type' => 'object',
                                    ],
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
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                    ],
                                    'id' => (object) [
                                        'type' => 'string',
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
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                            'users_relationship_pet' => (object) [
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
                                                                'const' => 'pets',
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                            [
                                                'type' => 'null',
                                            ],
                                        ],
                                    ],
                                    'links' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'related' => [
                                                'type' => 'string',
                                                'format' => 'uri',
                                            ],
                                            'self' => [
                                                'type' => 'string',
                                                'format' => 'uri',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'users_relationship_pets' => (object) [
                                'type' => 'object',
                                'properties' => [
                                    'data' => [
                                        'type' => 'array',
                                        'items' => [
                                            'allOf' => [
                                                [
                                                    '$ref' =>
                                                        '#/components/schemas/jsonApiResourceIdentifier',
                                                ],
                                                [
                                                    'properties' => [
                                                        'type' => [
                                                            'type' => 'string',
                                                            'const' => 'pets',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    'links' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'related' => [
                                                'type' => 'string',
                                                'format' => 'uri',
                                            ],
                                            'self' => [
                                                'type' => 'string',
                                                'format' => 'uri',
                                            ],
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
                                    'attributes' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => (object) [
                                                'type' => 'string',
                                            ],
                                        ],
                                        'required' => ['name'],
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'pet' => [
                                                '$ref' =>
                                                    '#/components/schemas/users_relationship_pet',
                                            ],
                                            'pets' => [
                                                '$ref' =>
                                                    '#/components/schemas/users_relationship_pets',
                                            ],
                                        ],
                                        'required' => ['pet', 'pets'],
                                    ],
                                    'id' => (object) [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'pattern' => '^[a-z0-9-]+$',
                                    ],
                                    'links' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'self' => [
                                                'type' => 'string',
                                                'format' => 'uri',
                                            ],
                                        ],
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
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                    ],
                                    'id' => (object) [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'pattern' => '^[a-z0-9-]+$',
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
                                    'attributes' => [
                                        'type' => 'object',
                                    ],
                                    'relationships' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'pet' => [
                                                '$ref' =>
                                                    '#/components/schemas/users_relationship_pet',
                                            ],
                                        ],
                                    ],
                                    'id' => [
                                        'type' => 'string',
                                        'minLength' => 3,
                                        'pattern' => '^[a-z0-9-]+$',
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
