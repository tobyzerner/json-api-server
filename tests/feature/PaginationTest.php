<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class PaginationTest extends AbstractTestCase
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

    public function test_resource_collections_are_paginated_to_20_records_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_types_can_specify_the_default_number_of_resources_per_page()
    {
        $this->markTestIncomplete();
    }

    public function test_types_can_disable_pagination_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_the_maximum_limit_is_50_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_types_can_specify_the_maximum_limit()
    {
        $this->markTestIncomplete();
    }

    public function test_types_can_remove_the_maximum_limit()
    {
        $this->markTestIncomplete();
    }
}
