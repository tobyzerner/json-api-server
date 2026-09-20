<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Endpoint;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\ShowRelated;
use Tobyz\JsonApiServer\Endpoint\ShowRelationship;
use Tobyz\JsonApiServer\Exception\Data\UnsupportedTypeException;
use Tobyz\JsonApiServer\Exception\Request\InvalidIncludeException;
use Tobyz\JsonApiServer\Exception\Request\InvalidQueryParameterException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockCollection;
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

        $this->assertArrayNotHasKey('data', $document['data']['relationships']['friends'] ?? []);
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

        $this->expectException(InvalidIncludeException::class);

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

        $this->expectException(UnsupportedTypeException::class);

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
        $this->api->resource(
            new MockResource('animals', models: [($friend = (object) ['id' => '1'])]),
        );

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Create::make()],
                fields: [
                    ToMany::make('friends')
                        ->type('creatures')
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
                    'relationships' => [
                        'friends' => ['data' => [['type' => 'animals', 'id' => '1']]],
                    ],
                ],
            ]),
        );

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_related_resource_query_parameters(): void
    {
        $api = $this->apiWithRelationshipParameters(ShowRelated::make());
        $response = $api->handle(
            $this->buildRequest('GET', '/users/1/friends?locale=fr&page[offset]=1&page[limit]=1'),
        );

        $document = json_decode($response->getBody(), true);
        $this->assertSame(['3'], array_column($document['data'], 'id'));
    }

    public function test_relationship_query_parameters(): void
    {
        $api = $this->apiWithRelationshipParameters(ShowRelationship::make());
        $response = $api->handle(
            $this->buildRequest('GET', '/users/1/relationships/friends?locale=fr&page[offset]=1&page[limit]=1'),
        );

        $document = json_decode($response->getBody(), true);
        $this->assertSame(['3'], array_column($document['data'], 'id'));
    }

    public function test_unknown_query_parameter(): void
    {
        $api = $this->apiWithRelationshipParameters(ShowRelated::make());

        $this->expectException(InvalidQueryParameterException::class);
        $api->handle($this->buildRequest('GET', '/users/1/friends?locale=fr&unknown=value'));
    }

    private function apiWithRelationshipParameters(Endpoint $endpoint): JsonApi
    {
        $api = new JsonApi();
        $api->resource(new class (
            'users',
            models: [(object) ['id' => '1', 'locale' => 'fr', 'friends' => [
                (object) ['id' => '1', 'locale' => 'en'],
                (object) ['id' => '2', 'locale' => 'fr'],
                (object) ['id' => '3', 'locale' => 'fr'],
            ]]],
            endpoints: [$endpoint->parameters([Parameter::make('locale')])],
            fields: [
                ToMany::make('friends')->type('users')->pagination(new OffsetPagination()),
            ],
        ) extends MockResource {
            public function find(string $id, Context $context): ?object
            {
                $model = parent::find($id, $context);
                return $model->locale === $context->parameter('locale') ? $model : null;
            }

            public function relatedQuery(object $model, ToMany $relationship, Context $context): ?object
            {
                $query = parent::relatedQuery($model, $relationship, $context);
                $query->models = array_filter(
                    $query->models,
                    fn($friend) => $friend->locale === $context->parameter('locale'),
                );
                return $query;
            }
        });

        return $api;
    }
}
