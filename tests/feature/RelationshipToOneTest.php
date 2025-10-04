<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\Data\UnsupportedTypeException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\Request\InvalidIncludeException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockCollection;
use Tobyz\Tests\JsonApiServer\MockResource;

class RelationshipToOneTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_to_one_with_linkage()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1']),
                    (object) ['id' => '2', 'friend' => $user1],
                ],
                endpoints: [Show::make()],
                fields: [ToOne::make('friend')->type('users')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/2'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '2',
                    'relationships' => ['friend' => ['data' => ['type' => 'users', 'id' => '1']]],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_to_one_with_linkage_null()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [ToOne::make('friend')->type('users')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '1',
                    'relationships' => ['friend' => ['data' => null]],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_to_one_without_linkage()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1']),
                    (object) ['id' => '2', 'friend' => $user1],
                ],
                endpoints: [Show::make()],
                fields: [
                    ToOne::make('friend')
                        ->type('users')
                        ->withoutLinkage(),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/2'));
        $document = json_decode($response->getBody(), true);

        $this->assertArrayNotHasKey('data', $document['data']['relationships']['friend'] ?? []);
    }

    public function test_to_one_not_includable()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [ToOne::make('friend')->type('users')],
            ),
        );

        $this->expectException(InvalidIncludeException::class);

        $this->api->handle($this->buildRequest('GET', '/users/1?include=friend'));
    }

    public function test_to_one_included()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1']),
                    (object) ['id' => '2', 'friend' => $user1],
                ],
                endpoints: [Show::make()],
                fields: [
                    ToOne::make('friend')
                        ->type('users')
                        ->includable(),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/2?include=friend'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'id' => '2',
                    'relationships' => ['friend' => ['data' => ['type' => 'users', 'id' => '1']]],
                ],
                'included' => [['type' => 'users', 'id' => '1']],
            ],
            $response->getBody(),
        );
    }

    public function test_to_one_create_and_include()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Create::make()],
                fields: [
                    ToOne::make('friend')
                        ->type('users')
                        ->writable()
                        ->includable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users?include=friend')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => ['friend' => ['data' => ['type' => 'users', 'id' => '1']]],
                ],
            ]),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'users',
                    'relationships' => ['friend' => ['data' => ['type' => 'users', 'id' => '1']]],
                ],
                'included' => [['type' => 'users', 'id' => '1']],
            ],
            $response->getBody(),
        );
    }

    public function test_to_one_create_invalid_type()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    ToOne::make('friend')
                        ->type('users')
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnsupportedTypeException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => ['friend' => ['data' => ['type' => 'test', 'id' => '1']]],
                ],
            ]),
        );
    }

    public function test_to_one_create_polymorphic()
    {
        $this->api->resource(
            new MockResource('animals', models: [($friend = (object) ['id' => '1'])]),
        );

        $this->api->resource(
            $resource = new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Create::make()],
                fields: [
                    ToOne::make('friend')
                        ->collection('creatures')
                        ->writable(),
                ],
            ),
        );

        $this->api->collection(
            new MockCollection('creatures', models: ['users' => [], 'animals' => [$friend]]),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => ['friend' => ['data' => ['type' => 'animals', 'id' => '1']]],
                ],
            ]),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame($friend, $resource->models[1]->friend);
    }

    public function test_to_one_create_collection()
    {
        $this->api->collection(
            new MockCollection('animals', [
                'dogs' => [(object) ['id' => '1']],
                'cats' => [($cat = (object) ['id' => '1'])],
            ]),
        );

        $this->api->resource(new MockResource('cats', models: [$cat]));

        $this->api->resource(
            $resource = new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Create::make()],
                fields: [
                    ToOne::make('pet')
                        ->collection('animals')
                        ->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => ['pet' => ['data' => ['type' => 'cats', 'id' => '1']]],
                ],
            ]),
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame($cat, $resource->models[1]->pet);
    }

    public function test_to_one_create_null_not_nullable()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Create::make()],
                fields: [
                    ToOne::make('friend')
                        ->type('users')
                        ->writable()
                        ->includable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'relationships' => ['friend' => ['data' => null]],
                ],
            ]),
        );
    }
}
