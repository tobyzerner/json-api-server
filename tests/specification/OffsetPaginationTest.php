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

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.1/#fetching-pagination
 */
class OffsetPaginationTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');

        $adapter = new MockAdapter(
            array_map(function ($i) {
                return (object) ['id' => (string) $i];
            }, range(1, 100))
        );

        $this->api->resourceType('articles', $adapter, function (Type $type) {
            $type->paginate(20);
        });
    }

    public function test_can_request_limit_on_resource_collection()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
                ->withQueryParams(['page' => ['limit' => '10']])
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertCount(10, $data);
    }

    public function test_can_request_offset_on_resource_collection()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
                ->withQueryParams(['page' => ['offset' => '5']])
        );

        $data = json_decode($response->getBody(), true)['data'] ?? null;

        $this->assertEquals('6', $data[0]['id'] ?? null);
    }

    public function test_pagination_links_are_correct_and_retain_query_parameters()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
                ->withQueryParams([
                    'page' => ['offset' => '40'],
                    'otherParam' => 'value',
                ])
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertEquals('/articles?otherParam=value', $links['first'] ?? null);
        $this->assertEquals('/articles?otherParam=value&page%5Boffset%5D=80', $links['last'] ?? null);
        $this->assertEquals('/articles?otherParam=value&page%5Boffset%5D=60', $links['next'] ?? null);
        $this->assertEquals('/articles?otherParam=value&page%5Boffset%5D=20', $links['prev'] ?? null);
    }

    public function test_next_pagination_link_is_not_included_on_last_page()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
                ->withQueryParams(['page' => ['offset' => '80']])
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertNull($links['next'] ?? null);
    }

    public function test_prev_pagination_link_is_not_included_on_first_page()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/articles')
                ->withQueryParams(['page' => ['offset' => '0']])
        );

        $links = json_decode($response->getBody(), true)['links'] ?? null;

        $this->assertNull($links['prev'] ?? null);
    }
}
