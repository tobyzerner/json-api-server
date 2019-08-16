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

class AttributeSortableTest extends AbstractTestCase
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

    public function test_attributes_can_be_sortable()
    {
        $attribute = null;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$attribute) {
            $attribute = $type->attribute('name')->sortable();
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['sort' => 'name'])
        );

        $this->assertContains([$attribute, 'asc'], $this->adapter->query->sort);
    }

    public function test_attributes_can_be_sortable_with_custom_logic()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->attribute('name')
                ->sortable(function ($query, $direction, $request) use (&$called) {
                    $this->assertSame($this->adapter->query, $query);
                    $this->assertEquals('asc', $direction);
                    $this->assertInstanceOf(ServerRequestInterface::class, $request);

                    $called = true;
                });
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['sort' => 'name'])
        );

        $this->assertTrue($called);
        $this->assertTrue(empty($this->adapter->query->sort));
    }

    public function test_attributes_are_not_sortable_by_default()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->attribute('name');
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['sort' => 'name'])
        );
    }

    public function test_attributes_can_be_explicitly_not_sortable()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->attribute('name')->notSortable();
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['sort' => 'name'])
        );
    }
}
