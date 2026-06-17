<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Endpoint\UpdateRelationship;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Header;
use Tobyz\JsonApiServer\Schema\Id;
use Tobyz\JsonApiServer\Schema\Meta;
use Tobyz\JsonApiServer\Schema\Parameter;
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

    public function test_generates_valid_resource_meta_schema_for_cursor_pagination()
    {
        $api = new JsonApi();

        $api->resource(new MockResource('articles', endpoints: [Index::make()->cursorPaginate()]));

        $definition = (new OpenApiGenerator())->generate($api);
        $items =
            $definition['paths']['/articles']['get']['responses']['200']['content'][
                'application/vnd.api+json'
            ]['schema']['properties']['data']['items'];

        $this->assertEquals(
            [
                'allOf' => [
                    ['$ref' => '#/components/schemas/articles'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'page' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'cursor' => ['type' => 'string'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            $items,
        );
    }

    public function test_async_create_schema_uses_top_level_content_and_location_headers()
    {
        $api = new JsonApi();

        $api->resource(new MockResource('jobs'));
        $api->resource(
            new MockResource(
                'photos',
                endpoints: [Create::make()->async('jobs', fn() => 'jobs/job-1')],
                fields: [Attribute::make('title')->writable()],
            ),
        );

        $definition = (new OpenApiGenerator())->generate($api);
        $response = $definition['paths']['/photos']['post']['responses']['202'];

        $this->assertSame(
            ['schema' => ['type' => 'string']],
            $response['headers']['Content-Location'],
        );
        $this->assertSame(['schema' => ['type' => 'string']], $response['headers']['Location']);
        $this->assertEquals(
            [
                'schema' => [
                    'type' => 'object',
                    'required' => ['data'],
                    'properties' => [
                        'data' => ['$ref' => '#/components/schemas/jobs'],
                        'included' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/jsonApiResource'],
                        ],
                    ],
                ],
            ],
            $response['content']['application/vnd.api+json'],
        );
    }

    public function test_typed_parameters_headers_and_meta_preserve_schema_definitions()
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [
                    Show::make()
                        ->parameters([
                            Parameter::make('pageSize')
                                ->type(Type\Integer::make())
                                ->required(),
                        ])
                        ->headers([Header::make('Retry-After')->type(Type\Integer::make())])
                        ->meta([Meta::make('count')->type(Type\Integer::make())]),
                ],
            ),
        );

        $definition = (new OpenApiGenerator())->generate($api);
        $operation = $definition['paths']['/users/{id}']['get'];

        $this->assertContains(
            [
                'name' => 'pageSize',
                'in' => 'query',
                'schema' => ['type' => 'integer'],
                'required' => true,
            ],
            $operation['parameters'],
        );

        $this->assertSame(
            ['schema' => ['type' => 'integer']],
            $operation['responses']['200']['headers']['Retry-After'],
        );

        $this->assertSame(
            ['type' => 'integer'],
            json_decode(
                json_encode(
                    $operation['responses']['200']['content']['application/vnd.api+json']['schema'][
                        'properties'
                    ]['meta']['properties']['count'],
                ),
                true,
            ),
        );
    }

    public function test_custom_schema_is_merged_for_delete_and_relationship_operations()
    {
        $api = new JsonApi();

        $api->resource(new MockResource('pets'));
        $api->resource(
            new MockResource(
                'users',
                endpoints: [
                    Delete::make()->schema(['summary' => 'Delete a user']),
                    UpdateRelationship::make()->schema(['summary' => 'Manage pet relationships']),
                ],
                fields: [
                    ToMany::make('pets')
                        ->type('pets')
                        ->writable(),
                ],
            ),
        );

        $definition = (new OpenApiGenerator())->generate($api);

        $this->assertSame(
            'Delete a user',
            $definition['paths']['/users/{id}']['delete']['summary'],
        );
        $this->assertSame(
            'Manage pet relationships',
            $definition['paths']['/users/{id}/relationships/pets']['patch']['summary'],
        );
        $this->assertSame(
            'Manage pet relationships',
            $definition['paths']['/users/{id}/relationships/pets']['post']['summary'],
        );
        $this->assertSame(
            'Manage pet relationships',
            $definition['paths']['/users/{id}/relationships/pets']['delete']['summary'],
        );
    }

    public function test_filter_types_are_included_in_openapi_parameters()
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                endpoints: [Index::make()],
                filters: [
                    CustomFilter::make('active', fn() => null)->type(Type\Boolean::make()),
                    CustomFilter::make('ids', fn() => null)->type(
                        Type\Arr::make()
                            ->items(Type\Integer::make())
                            ->commaSeparated(),
                    ),
                    CustomFilter::make('score', fn() => null)
                        ->type(Type\Number::make())
                        ->operators(['eq', 'gt']),
                    CustomFilter::make('range', fn() => null)
                        ->type(
                            Type\Obj::make()
                                ->property('min', Type\Integer::make())
                                ->property('max', Type\Integer::make()),
                        )
                        ->operators(['eq', 'gt']),
                    CustomFilter::make('raw', fn() => null)->operators(['eq', 'gt']),
                ],
            ),
        );

        $definition = (new OpenApiGenerator())->generate($api);
        $parameters = $definition['paths']['/users']['get']['parameters'];

        $byName = [];
        foreach ($parameters as $parameter) {
            $byName[$parameter['name']] = $parameter;
        }

        $this->assertSame(
            ['filter'],
            array_values(
                array_filter(
                    array_keys($byName),
                    fn($name) => $name === 'filter' || str_starts_with($name, 'filter['),
                ),
            ),
        );

        $this->assertSame('deepObject', $byName['filter']['style']);
        $this->assertTrue($byName['filter']['explode']);

        $filterProperties = $byName['filter']['schema']['properties'];

        $this->assertSame(['type' => 'boolean'], $filterProperties['active']);
        $this->assertSame(
            [
                'type' => 'array',
                'x-jsonapi-filter-comma-separated' => true,
                'items' => ['type' => 'integer'],
            ],
            $filterProperties['ids'],
        );
        $this->assertSame(['eq', 'gt'], $filterProperties['score']['x-jsonapi-filter-operators']);

        $rangeSchema = $filterProperties['range'];
        $rangeOperatorSchema = $rangeSchema['oneOf'][1];

        $this->assertSame('object', $rangeOperatorSchema['type']);
        $this->assertSame(1, $rangeOperatorSchema['minProperties']);
        $this->assertFalse($rangeOperatorSchema['additionalProperties']);
        $this->assertSame(
            [
                'type' => 'object',
                'properties' => [
                    'min' => ['type' => 'integer'],
                    'max' => ['type' => 'integer'],
                ],
            ],
            $rangeOperatorSchema['properties']['gt'],
        );
        $this->assertSame($rangeOperatorSchema, $rangeSchema['oneOf'][0]['allOf'][1]['not']);

        $rawSchema = $filterProperties['raw'];

        $this->assertSame(['not' => ['type' => 'object']], $rawSchema['oneOf'][0]);
        $rawOperatorSchema = $rawSchema['oneOf'][1];

        $this->assertSame('object', $rawOperatorSchema['type']);
        $this->assertSame(1, $rawOperatorSchema['minProperties']);
        $this->assertFalse($rawOperatorSchema['additionalProperties']);
        $this->assertInstanceOf(\stdClass::class, $rawOperatorSchema['properties']['eq']);
        $this->assertInstanceOf(\stdClass::class, $rawOperatorSchema['properties']['gt']);
        $this->assertSame('{}', json_encode($rawOperatorSchema['properties']['eq']));
    }

    public function test_untyped_filter_schema_serializes_as_object()
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                endpoints: [Index::make()],
                filters: [
                    CustomFilter::make('products', fn() => null),
                    CustomFilter::make('mine', fn() => null),
                ],
            ),
        );

        $definition = (new OpenApiGenerator())->generate($api);
        $parameters = $definition['paths']['/users']['get']['parameters'];

        $filterParameter = null;
        foreach ($parameters as $parameter) {
            if ($parameter['name'] === 'filter') {
                $filterParameter = $parameter;
                break;
            }
        }

        $filterProperties = $filterParameter['schema']['properties'];

        $this->assertInstanceOf(\stdClass::class, $filterProperties['products']);
        $this->assertInstanceOf(\stdClass::class, $filterProperties['mine']);
        $this->assertSame('{"products":{},"mine":{}}', json_encode($filterProperties));
    }
}
