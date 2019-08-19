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

class CountabilityTest extends AbstractTestCase
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

    public function test_total_number_of_resources_and_last_pagination_link_is_included_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_types_can_be_made_uncountable()
    {
        $this->markTestIncomplete();
    }

    public function test_types_can_be_made_countable()
    {
        $this->markTestIncomplete();
    }
}
