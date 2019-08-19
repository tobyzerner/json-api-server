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

use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class ScopesTest extends AbstractTestCase
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

    public function test_scopes_are_applied_to_the_resource_listing_query()
    {
        $this->markTestIncomplete();
    }

    public function test_scopes_are_applied_to_the_show_resource_query()
    {
        $this->markTestIncomplete();
    }

    public function test_scopes_are_applied_to_the_update_resource_query()
    {
        $this->markTestIncomplete();
    }

    public function test_scopes_are_applied_to_the_delete_resource_query()
    {
        $this->markTestIncomplete();
    }
}
