<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-relationships
 */
class FetchingRelationshipsTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_fetch_relationship_for_to_one_returns_identifier_object()
    {
        $this->api->resource(
            new MockResource('users', models: [($author = (object) ['id' => '1'])]),
        );

        $this->api->resource(
            new MockResource(
                'articles',
                models: [(object) ['id' => '1', 'author' => $author]],
                endpoints: [Show::make()],
                fields: [ToOne::make('author')->type('users')],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/relationships/author'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => ['type' => 'users', 'id' => '1'],
                'links' => [
                    'self' => '/articles/1/relationships/author',
                    'related' => '/articles/1/author',
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_relationship_for_to_one_empty_returns_null()
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

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/relationships/author'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => null,
                'links' => [
                    'self' => '/articles/1/relationships/author',
                    'related' => '/articles/1/author',
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_relationship_for_to_many_returns_resource_identifiers()
    {
        $this->api->resource(
            new MockResource(
                'comments',
                models: [
                    ($comment1 = (object) ['id' => '1']),
                    ($comment2 = (object) ['id' => '2']),
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
            $this->buildRequest('GET', '/articles/1/relationships/comments'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    ['type' => 'comments', 'id' => '1'],
                    ['type' => 'comments', 'id' => '2'],
                ],
                'links' => [
                    'self' => '/articles/1/relationships/comments',
                    'related' => '/articles/1/comments',
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_relationship_for_to_many_empty_returns_empty_array()
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

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1/relationships/comments'),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [],
                'links' => [
                    'self' => '/articles/1/relationships/comments',
                    'related' => '/articles/1/comments',
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_fetch_relationship_document_can_paginate()
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
            $this->buildRequest(
                'GET',
                '/articles/1/relationships/comments?page[offset]=1&page[limit]=1',
            ),
        );

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [['type' => 'comments', 'id' => '2']],
                'links' => [
                    'self' => '/articles/1/relationships/comments',
                    'related' => '/articles/1/comments',
                    'first' => '/articles/1/relationships/comments?page%5Blimit%5D=1',
                    'prev' => '/articles/1/relationships/comments?page%5Blimit%5D=1',
                ],
                'meta' => ['page' => ['total' => 2]],
            ],
            $response->getBody(),
        );
    }
}
