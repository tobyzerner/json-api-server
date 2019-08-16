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

class AttributeFilterableTest extends AbstractTestCase
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

    public function test_attributes_are_not_filterable_by_default()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->attribute('field');
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['field' => 'Toby']])
        );
    }

    public function test_attributes_can_be_filterable()
    {
        $attribute = null;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$attribute) {
            $attribute = $type->attribute('name')->filterable();
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['name' => 'Toby']])
        );

        $this->assertContains([$attribute, 'Toby'], $this->adapter->query->filter);
    }

    public function test_attributes_can_be_filterable_with_custom_logic()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->attribute('name')
                ->filterable(function ($query, $value, $request) use (&$called) {
                    $this->assertSame($this->adapter->query, $query);
                    $this->assertEquals('Toby', $value);
                    $this->assertInstanceOf(ServerRequestInterface::class, $request);

                    $called = true;
                });
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['name' => 'Toby']])
        );

        $this->assertTrue($called);
        $this->assertTrue(empty($this->adapter->query->filter));
    }

    public function test_attributes_can_be_explicitly_not_filterable()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->attribute('name')->notFilterable();
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['filter' => ['name' => 'Toby']])
        );
    }
}
