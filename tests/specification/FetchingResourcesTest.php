<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockCollection;
use Tobyz\Tests\JsonApiServer\MockResource;
use Tobyz\Tests\JsonApiServer\MockSort;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-resources
 */
class FetchingResourcesTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_data_for_resource_collection_is_array_of_resource_objects()
    {
        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1'], (object) ['id' => '2']],
                endpoints: [Index::make()],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/articles'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    ['type' => 'articles', 'id' => '1'],
                    ['type' => 'articles', 'id' => '2'],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_data_for_empty_resource_collection_is_empty_array()
    {
        $this->api->resource(new MockResource('articles', endpoints: [Index::make()]));

        $response = $this->api->handle($this->buildRequest('GET', '/articles'));

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function test_data_for_individual_resource_is_resource_object()
    {
        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/articles/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['type' => 'articles', 'id' => '1']],
            $response->getBody(),
        );
    }

    public function test_not_found_error_if_resource_type_does_not_exist()
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle($this->buildRequest('GET', '/articles/1'));
    }

    public function test_not_found_error_if_resource_does_not_exist()
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->api->resource(new MockResource('articles', endpoints: [Show::make()]));

        $this->api->handle($this->buildRequest('GET', '/articles/404'));
    }

    public function test_data_for_collection_is_array_of_resource_objects()
    {
        $this->api->resource(new MockResource('dogs'));
        $this->api->resource(new MockResource('cats'));

        $this->api->collection(
            new MockCollection(
                'animals',
                models: [
                    'dogs' => [(object) ['id' => '1']],
                    'cats' => [(object) ['id' => '1']],
                ],
                endpoints: [Index::make()],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/animals'));

        $this->assertJsonApiDocumentSubset(
            ['data' => [['type' => 'dogs', 'id' => '1'], ['type' => 'cats', 'id' => '1']]],
            $response->getBody(),
        );
    }

    public function test_fetch_related_resource_with_include_for_to_one_relationship()
    {
        $this->api->resource(new MockResource('pets', models: [($pet = (object) ['id' => '1'])]));

        $this->api->resource(
            new MockResource(
                'users',
                models: [($user = (object) ['id' => '1', 'name' => 'Toby', 'pet' => $pet])],
                fields: [Attribute::make('name'), ToOne::make('pet')->includable()],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'author' => $user]],
                endpoints: [Show::make()],
                fields: [ToOne::make('author')->type('users')],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/author?include=pet'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => ['type' => 'users', 'id' => '1', 'attributes' => ['name' => 'Toby']],
                'included' => [['type' => 'pets', 'id' => '1']],
                'links' => ['self' => '/articles/1/author'],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_related_resource_for_to_one_relationships_empty()
    {
        $this->api->resource(new MockResource('users'));

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'author' => null]],
                endpoints: [Show::make()],
                fields: [ToOne::make('author')->type('users')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/articles/1/author'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => null,
                'links' => ['self' => '/articles/1/author'],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_related_resources_with_include_for_to_many_relationship()
    {
        $this->api->resource(new MockResource('users', models: [($user = (object) ['id' => '1'])]));

        $this->api->resource(
            new MockResource(
                'comments',
                models: [
                    ($comment1 = (object) ['id' => '1', 'body' => 'hello', 'author' => $user]),
                    ($comment2 = (object) ['id' => '2', 'body' => 'test', 'author' => $user]),
                ],
                fields: [
                    Attribute::make('body'),
                    ToOne::make('author')
                        ->type('users')
                        ->includable(),
                ],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'comments' => [$comment1, $comment2]]],
                endpoints: [Show::make()],
                fields: [ToMany::make('comments')],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/comments?include=author'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    ['type' => 'comments', 'id' => '1', 'attributes' => ['body' => 'hello']],
                    ['type' => 'comments', 'id' => '2', 'attributes' => ['body' => 'test']],
                ],
                'included' => [['type' => 'users', 'id' => '1']],
                'links' => ['self' => '/articles/1/comments'],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_related_resources_for_to_many_relationship_empty()
    {
        $this->api->resource(new MockResource('comments'));

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'comments' => []]],
                endpoints: [Show::make()],
                fields: [ToMany::make('comments')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/articles/1/comments'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [],
                'links' => ['self' => '/articles/1/comments'],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_related_resources_can_sort()
    {
        $this->api->resource(
            new MockResource(
                'comments',
                models: [
                    ($comment1 = (object) ['id' => '1', 'body' => 'alpha']),
                    ($comment2 = (object) ['id' => '2', 'body' => 'zulu']),
                ],
                fields: [Attribute::make('body')],
                sorts: [MockSort::make('body')],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'comments' => [$comment1, $comment2]]],
                endpoints: [Show::make()],
                fields: [ToMany::make('comments')],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/comments?sort=-body'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    ['type' => 'comments', 'id' => '2'],
                    ['type' => 'comments', 'id' => '1'],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_related_resources_can_filter()
    {
        $this->api->resource(
            new MockResource(
                'comments',
                models: [
                    ($comment1 = (object) ['id' => '1', 'body' => 'match']),
                    ($comment2 = (object) ['id' => '2', 'body' => 'other']),
                ],
                fields: [Attribute::make('body')],
                filters: [
                    CustomFilter::make('body', function ($query, $value) {
                        $query->models = array_values(
                            array_filter($query->models, fn($model) => $model->body === $value),
                        );
                    }),
                ],
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'comments' => [$comment1, $comment2]]],
                endpoints: [Show::make()],
                fields: [ToMany::make('comments')],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/comments?filter[body]=match'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [['type' => 'comments', 'id' => '1']],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_related_resources_can_paginate()
    {
        $this->api->resource(
            new MockResource(
                'comments',
                models: [
                    ($comment1 = (object) ['id' => '1', 'body' => 'alpha']),
                    ($comment2 = (object) ['id' => '2', 'body' => 'zulu']),
                ],
                fields: [Attribute::make('body')],
                pagination: new OffsetPagination(),
            ),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'comments' => [$comment1, $comment2]]],
                endpoints: [Show::make()],
                fields: [ToMany::make('comments')],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/comments?page[offset]=1&page[limit]=1'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [['type' => 'comments', 'id' => '2']],
                'links' => [
                    'self' => '/articles/1/comments',
                    'first' => '/articles/1/comments?page%5Blimit%5D=1',
                    'prev' => '/articles/1/comments?page%5Blimit%5D=1',
                ],
                'meta' => ['page' => ['total' => 2]],
            ],
            $response->getBody(),
        );
    }
}
