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
 * @see https://jsonapi.org/format/#fetching-resources
 */
class FetchingResourcesTest extends AbstractTestCase
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

    public function test_data_for_resource_collection_is_array_of_resource_objects()
    {
        $this->markTestIncomplete();
    }

    public function test_data_for_empty_resource_collection_is_empty_array()
    {
        $this->markTestIncomplete();
    }

    public function test_data_for_individual_resource_is_resource_object()
    {
        $this->markTestIncomplete();
    }

    public function test_not_found_error_if_resource_type_does_not_exist()
    {
        $this->markTestIncomplete();
    }

    public function test_not_found_error_if_resource_does_not_exist()
    {
        $this->markTestIncomplete();
    }

    public function test_resource_collection_document_contains_self_link()
    {
        $this->markTestIncomplete();
    }

    public function test_resource_document_contains_self_link()
    {
        $this->markTestIncomplete();
    }
}
