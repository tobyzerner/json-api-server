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
 * @see https://jsonapi.org/format/1.0/#crud-creating
 */
class CreatingResourcesTest extends AbstractTestCase
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

    public function test_bad_request_error_if_body_does_not_contain_data_type()
    {
        $this->markTestIncomplete();
    }

    public function test_bad_request_error_if_relationship_does_not_contain_data()
    {
        $this->markTestIncomplete();
    }

    public function test_forbidden_error_if_client_generated_id_provided()
    {
        $this->markTestIncomplete();
    }

    public function test_created_response_if_resource_successfully_created()
    {
        $this->markTestIncomplete();
    }

    public function test_created_response_includes_created_data()
    {
        $this->markTestIncomplete();
    }

    public function test_created_response_includes_location_header_and_matches_self_link()
    {
        $this->markTestIncomplete();
    }

    public function test_not_found_error_if_references_resource_that_does_not_exist()
    {
        $this->markTestIncomplete();
    }

    public function test_conflict_error_if_type_does_not_match_endpoint()
    {
        $this->markTestIncomplete();
    }
}
