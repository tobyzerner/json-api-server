<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use stdClass;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class RelationshipToManyTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_to_many_with_linkage()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1']),
                    ($user2 = (object) ['id' => '2']),
                    (object) ['id' => '3', 'friends' => [$user1, $user2]],
                ],
                endpoints: [Show::make()],
                fields: [
                    ToMany::make('friends')
                        ->withLinkage()
                        ->type('users'),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/3'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '3',
                    'relationships' => [
                        'friends' => [
                            'data' => [
                                ['type' => 'users', 'id' => '1'],
                                ['type' => 'users', 'id' => '2'],
                            ],
                        ],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_empty_to_many_with_linkage()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '3', 'friends' => []]],
                endpoints: [Show::make()],
                fields: [
                    ToMany::make('friends')
                        ->withLinkage()
                        ->type('users'),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/3'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '3',
                    'relationships' => [
                        'friends' => ['data' => []],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_to_many_without_linkage()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1']),
                    ($user2 = (object) ['id' => '2']),
                    (object) ['id' => '3', 'friends' => [$user1, $user2]],
                ],
                endpoints: [Show::make()],
                fields: [ToMany::make('friends')->type('users')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/3'));
        $document = json_decode($response->getBody(), true);

        $this->assertArrayNotHasKey('friends', $document['data']['relationships'] ?? []);
    }

    public function test_to_many_not_includable()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [ToMany::make('friends')->type('users')],
            ),
        );

        $this->expectException(BadRequestException::class);

        $this->api->handle($this->buildRequest('GET', '/users/1?include=friend'));
    }

    public function test_to_many_included()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1']),
                    ($user2 = (object) ['id' => '2']),
                    (object) ['id' => '3', 'friends' => [$user1, $user2]],
                ],
                endpoints: [Show::make()],
                fields: [
                    ToMany::make('friends')
                        ->type('users')
                        ->includable(),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/3?include=friends'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '3',
                    'relationships' => [
                        'friends' => [
                            'data' => [
                                ['type' => 'users', 'id' => '1'],
                                ['type' => 'users', 'id' => '2'],
                            ],
                        ],
                    ],
                ],
                'included' => [['type' => 'users', 'id' => '1'], ['type' => 'users', 'id' => '2']],
            ],
            $response->getBody(),
        );
    }

    public function test_to_many_create_and_include()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1'], (object) ['id' => '2']],
                endpoints: [Create::make()],
                fields: [
                    ToMany::make('friends')
                        ->type('users')
                        ->writable()
                        ->includable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users?include=friends')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => [
                        'friends' => [
                            'data' => [
                                ['type' => 'users', 'id' => '1'],
                                ['type' => 'users', 'id' => '2'],
                            ],
                        ],
                    ],
                ],
            ]),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'relationships' => [
                        'friends' => [
                            'data' => [
                                ['type' => 'users', 'id' => '1'],
                                ['type' => 'users', 'id' => '2'],
                            ],
                        ],
                    ],
                ],
                'included' => [['type' => 'users', 'id' => '1'], ['type' => 'users', 'id' => '2']],
            ],
            $response->getBody(),
        );
    }

    public function test_to_many_create_invalid_type()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    ToMany::make('friends')
                        ->type('users')
                        ->writable(),
                ],
            ),
        );

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => [
                        'friends' => [
                            'data' => [['type' => 'test', 'id' => '1']],
                        ],
                    ],
                ],
            ]),
        );
    }

    public function test_to_many_create_polymorphic()
    {
        $this->api->resource(new MockResource('animals', models: [(object) ['id' => '1']]));

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Create::make()],
                fields: [
                    ToMany::make('friends')
                        ->type(['users', stdClass::class => 'animals'])
                        ->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => [
                        'friends' => ['data' => [['type' => 'animals', 'id' => '1']]],
                    ],
                ],
            ]),
        );

        $this->assertEquals(201, $response->getStatusCode());
    }
}
