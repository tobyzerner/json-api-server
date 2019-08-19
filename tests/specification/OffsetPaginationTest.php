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
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.0/#fetching-pagination
 * @todo Create a profile for offset pagination strategy
 */
class OffsetPaginationTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    /**
     * @var MockAdapter
     */
    private $adapter;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');

        $this->adapter = new MockAdapter();
    }

    public function test_can_request_limit_on_resource_collection()
    {
        $this->markTestIncomplete();
    }

    public function test_can_request_offset_on_resource_collection()
    {
        $this->markTestIncomplete();
    }

    public function test_first_pagination_link_is_correct()
    {
        $this->markTestIncomplete();
    }

    public function test_last_pagination_link_is_correct()
    {
        $this->markTestIncomplete();
    }

    public function test_next_pagination_link_is_correct()
    {
        $this->markTestIncomplete();
    }

    public function test_next_pagination_link_is_not_included_on_last_page()
    {
        $this->markTestIncomplete();
    }

    public function test_prev_pagination_link_is_correct()
    {
        $this->markTestIncomplete();
    }

    public function test_prev_pagination_link_is_not_included_on_last_page()
    {
        $this->markTestIncomplete();
    }

    public function test_pagination_links_retain_other_query_parameters()
    {
        $this->markTestIncomplete();
    }
}
