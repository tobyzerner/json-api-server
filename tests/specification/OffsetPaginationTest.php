<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-pagination
 */
class OffsetPaginationTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'articles',
                models: array_map(fn($i) => (object) ['id' => (string) $i], range(1, 100)),
                endpoints: [Index::make()->paginate()],
            ),
        );
    }

    public function test_can_request_limit_on_resource_collection()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams(['page' => ['limit' => '10']]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertCount(10, $data);
    }

    public function test_can_request_offset_on_resource_collection()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams(['page' => ['offset' => '5']]),
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertEquals('6', $data[0]['id'] ?? null);
    }

    public function test_pagination_links_are_correct_and_retain_query_parameters()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['offset' => '40'],
                'fields[test]' => 'value',
            ]),
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertEquals('/articles?fields%5Btest%5D=value', $links['first'] ?? null);
        $this->assertEquals(
            '/articles?fields%5Btest%5D=value&page%5Boffset%5D=80',
            $links['last'] ?? null,
        );
        $this->assertEquals(
            '/articles?fields%5Btest%5D=value&page%5Boffset%5D=60',
            $links['next'] ?? null,
        );
        $this->assertEquals(
            '/articles?fields%5Btest%5D=value&page%5Boffset%5D=20',
            $links['prev'] ?? null,
        );
    }

    public function test_next_pagination_link_is_not_included_on_last_page()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams([
                'page' => ['offset' => '80'],
            ]),
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertNull($links['next'] ?? null);
    }

    public function test_prev_pagination_link_is_not_included_on_first_page()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')->withQueryParams(['page' => ['offset' => '0']]),
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertNull($links['prev'] ?? null);
    }
}
