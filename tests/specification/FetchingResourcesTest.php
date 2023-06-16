<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

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
}
