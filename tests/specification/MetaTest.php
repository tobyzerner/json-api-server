<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Field\ToOne;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/#document-meta
 */
class MetaTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_resource_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Show::make()],
                meta: [Attribute::make('foo')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['meta' => ['foo' => 'bar']]],
            $response->getBody(),
        );
    }

    public function test_to_one_relationship_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [ToOne::make('pet')->meta([Attribute::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['relationships' => ['pet' => ['meta' => ['foo' => 'bar']]]]],
            $response->getBody(),
        );
    }

    public function test_to_many_relationship_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [ToMany::make('pets')->meta([Attribute::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['relationships' => ['pets' => ['meta' => ['foo' => 'bar']]]]],
            $response->getBody(),
        );
    }

    public function test_show_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Show::make()->meta([Attribute::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_update_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Update::make()->meta([Attribute::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')->withParsedBody([
                'data' => ['type' => 'users', 'id' => '1'],
            ]),
        );

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_delete_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'foo' => 'bar']],
                endpoints: [Delete::make()->meta([Attribute::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('DELETE', '/users/1'));

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_index_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Index::make()->meta([Attribute::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users'));

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_create_endpoint_meta()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()->meta([Attribute::make('foo')->get(fn() => 'bar')])],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users'],
            ]),
        );

        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }
}
