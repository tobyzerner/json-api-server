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
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class FieldFiltersTest extends AbstractTestCase
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

    public function test_resources_can_be_filtered_by_id()
    {
        $this->markTestIncomplete();
    }

    public function test_attributes_are_not_filterable_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_attributes_can_be_explicitly_not_filterable()
    {
        $this->markTestIncomplete();
    }

    public function test_attributes_can_be_filterable_by_their_value()
    {
        $this->markTestIncomplete();
    }

    public function test_attributes_can_be_filterable_with_custom_logic()
    {
        $this->markTestIncomplete();
    }

    public function test_attributes_filterable_callback_receives_correct_parameters()
    {
        $this->markTestIncomplete();
    }

    // to_one, to_many...

    public function test_types_can_have_custom_filters()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->filter('name', function (...$args) use (&$called) {
                $this->assertSame($this->adapter->query, $args[0]);
                $this->assertEquals('value', $args[1]);
                $this->assertInstanceOf(Context::class, $args[2]);

                $called = true;
            });
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['name' => 'value']])
        );

        $this->assertTrue($called);
    }

    public function test_types_can_have_a_default_filter()
    {
        $this->markTestIncomplete();
    }
}
