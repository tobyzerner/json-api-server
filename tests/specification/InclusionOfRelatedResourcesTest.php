<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Str;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
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
                fields: [Str::make('name')],
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
                    Str::make('title'),
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
}
