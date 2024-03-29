<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockCollection;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-includes
 */
class InclusionOfRelatedResourcesTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    ($user1 = (object) ['id' => '1', 'name' => 'Toby']),
                    ($user2 = (object) ['id' => '2', 'name' => 'Franz']),
                ],
                fields: [Attribute::make('name')],
            ),
        );

        $this->api->resource(
            new MockResource(
                'comments',
                models: [
                    ($comment1 = (object) ['id' => '1', 'author' => $user1]),
                    ($comment2 = (object) ['id' => '2', 'author' => $user2]),
                ],
                fields: [
                    ToOne::make('author')
                        ->type('users')
                        ->includable(),
                ],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [
                    '1' => (object) [
                        'id' => '1',
                        'title' => 'foo',
                        'author' => $user1,
                        'comments' => [$comment1, $comment2],
                    ],
                ],
                endpoints: [Show::make(), Index::make()],
                fields: [
                    Attribute::make('title'),
                    ToOne::make('author')
                        ->type('users')
                        ->includable(),
                    ToMany::make('comments')->includable(),
                ],
            ),
        );
    }

    public function test_relationship_inclusion_on_show_endpoint()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1?include=author,comments.author'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'type' => 'articles',
                    'id' => '1',
                    'attributes' => ['title' => 'foo'],
                    'relationships' => [
                        'author' => [
                            'data' => ['type' => 'users', 'id' => '1'],
                        ],
                        'comments' => [
                            'data' => [
                                ['type' => 'comments', 'id' => '1'],
                                ['type' => 'comments', 'id' => '2'],
                            ],
                        ],
                    ],
                ],
                'included' => [
                    [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => ['name' => 'Toby'],
                    ],
                    [
                        'type' => 'comments',
                        'id' => '1',
                        'relationships' => [
                            'author' => ['data' => ['type' => 'users', 'id' => '1']],
                        ],
                    ],
                    [
                        'type' => 'comments',
                        'id' => '2',
                        'relationships' => [
                            'author' => ['data' => ['type' => 'users', 'id' => '2']],
                        ],
                    ],
                    [
                        'type' => 'users',
                        'id' => '2',
                        'attributes' => ['name' => 'Franz'],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_relationship_inclusion_on_list_endpoint()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles?include=author,comments.author'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    [
                        'type' => 'articles',
                        'id' => '1',
                        'attributes' => ['title' => 'foo'],
                        'relationships' => [
                            'author' => [
                                'data' => ['type' => 'users', 'id' => '1'],
                            ],
                            'comments' => [
                                'data' => [
                                    ['type' => 'comments', 'id' => '1'],
                                    ['type' => 'comments', 'id' => '2'],
                                ],
                            ],
                        ],
                    ],
                ],
                'included' => [
                    [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => ['name' => 'Toby'],
                    ],
                    [
                        'type' => 'comments',
                        'id' => '1',
                        'relationships' => [
                            'author' => ['data' => ['type' => 'users', 'id' => '1']],
                        ],
                    ],
                    [
                        'type' => 'comments',
                        'id' => '2',
                        'relationships' => [
                            'author' => ['data' => ['type' => 'users', 'id' => '2']],
                        ],
                    ],
                    [
                        'type' => 'users',
                        'id' => '2',
                        'attributes' => ['name' => 'Franz'],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_relationship_inclusion_for_polymorphic_relationship()
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                models: [($user1 = (object) ['id' => '1', 'name' => 'Toby'])],
                fields: [Attribute::make('name')],
            ),
        );

        $api->resource(
            new MockResource(
                'posts',
                models: [($post1 = (object) ['id' => '1', 'author' => $user1])],
                fields: [
                    ToOne::make('author')
                        ->type('users')
                        ->includable(),
                ],
            ),
        );

        $api->collection(
            new MockCollection('subjects', [
                'users' => [$user1],
                'posts' => [$post1],
            ]),
        );

        $api->resource(
            new MockResource(
                'notifications',
                models: [
                    ((object) ['id' => '1', 'subject' => $post1]),
                    ((object) ['id' => '2', 'subject' => $user1]),
                ],
                endpoints: [Index::make()],
                fields: [
                    ToOne::make('subject')
                        ->collection('subjects')
                        ->includable(),
                ],
            ),
        );

        $response = $api->handle(
            $this->buildRequest('GET', '/notifications?include=subject.author'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    [
                        'type' => 'notifications',
                        'id' => '1',
                        'relationships' => [
                            'subject' => [
                                'data' => ['type' => 'posts', 'id' => '1'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'notifications',
                        'id' => '2',
                        'relationships' => [
                            'subject' => [
                                'data' => ['type' => 'users', 'id' => '1'],
                            ],
                        ],
                    ],
                ],
                'included' => [
                    [
                        'type' => 'posts',
                        'id' => '1',
                    ],
                    [
                        'type' => 'users',
                        'id' => '1',
                    ],
                ],
            ],
            $response->getBody(),
        );
    }
}
