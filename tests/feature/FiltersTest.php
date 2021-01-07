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

use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class FiltersTest extends AbstractTestCase
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
        $this->api = new JsonApi('/');

        $models = [];
        for ($i = 1; $i <= 100; $i++) {
            $models[] = (object) ['type' => 'users', 'id' => $i];
        }

        $this->adapter = new MockAdapter($models, 'users');
    }

    public function test_resources_can_be_filtered_by_id()
    {
        $this->api->resource('users', $this->adapter);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['id' => '1,2']])
        );

        $this->assertContains(['ids', ['1', '2']], $this->adapter->query->filter ?? null);
    }

    public function test_attributes_are_not_filterable_by_default()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->attribute('test');
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['test' => 'value']])
        );
    }

    public function test_attributes_can_be_explicitly_filterable()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) use (&$attribute) {
            $attribute = $type->attribute('test')->filterable();
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['test' => 'value']])
        );

        $this->assertContains([$attribute, '=', 'value'], $this->adapter->query->filter ?? null);
    }

    // public function test_attributes_can_be_conditionally_filterable()
    // {
    //     $this->api->resource('users', $this->adapter, function (Type $type) use (&$attribute) {
    //         $attribute = $type->attribute('test')->filterable();
    //     });
    //
    //     $this->api->handle(
    //         $this->buildRequest('GET', '/users')
    //             ->withQueryParams(['filter' => ['test' => 'value']])
    //     );
    //
    //     $this->assertContains([$attribute, '=', 'value'], $this->adapter->query->filter ?? null);
    // }

    public function test_attributes_can_be_filterable_by_their_value()
    {
        $this->markTestIncomplete();
    }

    // to_one, to_many...

    public function test_types_can_have_custom_filters()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->filter('name', function ($query, $value, Context $context) use (&$called) {
                $this->assertSame($this->adapter->query, $query);
                $this->assertEquals('value', $value);

                $called = true;
            });
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['name' => 'value']])
        );

        $this->assertTrue($called);
    }
}
