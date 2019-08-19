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
 * @see https://jsonapi.org/format/1.0/#fetching-sparse-fieldsets
 */
class SparseFieldsetsTest extends AbstractTestCase
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

    public function test_can_request_sparse_fieldsets_for_a_type()
    {
        $this->markTestIncomplete();
    }

    public function test_can_request_sparse_fieldsets_for_multiple_types()
    {
        $this->markTestIncomplete();
    }

    public function test_can_request_sparse_fieldsets_on_resource_collections()
    {
        $this->markTestIncomplete();
    }

    public function test_can_request_sparse_fieldsets_on_create()
    {
        $this->markTestIncomplete();
    }

    public function test_can_request_sparse_fieldsets_on_update()
    {
        $this->markTestIncomplete();
    }
}
