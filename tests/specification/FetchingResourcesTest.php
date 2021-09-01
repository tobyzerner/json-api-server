<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-resources
 */
class FetchingResourcesTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');
    }

    public function test_data_for_resource_collection_is_array_of_resource_objects()
    {
        $adapter = new MockAdapter([
            (object) ['id' => '1'],
            (object) ['id' => '2'],
        ]);

        $this->api->resourceType('articles', $adapter);

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
        );

        $this->assertJsonApiDocumentSubset([
            'data' => [
                ['type' => 'articles', 'id' => '1'],
                ['type' => 'articles', 'id' => '2'],
            ]
        ], $response->getBody());
    }

    public function test_data_for_empty_resource_collection_is_empty_array()
    {
        $this->api->resourceType('articles', new MockAdapter());

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function test_data_for_individual_resource_is_resource_object()
    {
        $adapter = new MockAdapter([
            (object) ['id' => '1'],
        ]);

        $this->api->resourceType('articles', $adapter);

        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles/1')
        );

        $this->assertJsonApiDocumentSubset([
            'data' => ['type' => 'articles', 'id' => '1'],
        ], $response->getBody());
    }

    public function test_not_found_error_if_resource_type_does_not_exist()
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/articles/1')
        );
    }

    public function test_not_found_error_if_resource_does_not_exist()
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->api->resourceType('articles', new MockAdapter());

        $this->api->handle(
            $this->buildRequest('GET', '/articles/404')
        );
    }
}
