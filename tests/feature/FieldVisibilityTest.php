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

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class FieldVisibilityTest extends AbstractTestCase
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

        $this->adapter = new MockAdapter([
            '1' => (object) ['id' => '1']
        ]);
    }

    public function test_attributes_are_visible_by_default()
    {
        $this->api->resource('users', new MockAdapter, function (Type $type) {
            $type->attribute('visible');
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody(), true);
        $attributes = $document['data']['attributes'] ?? [];

        $this->assertArrayHasKey('visible', $attributes);
    }

    public function test_attributes_can_be_explicitly_visible()
    {
        $this->markTestIncomplete();

        $this->api->resource('users', new MockAdapter, function (Type $type) {
            $type->attribute('visibleAttribute')->visible();
            $type->hasOne('visibleHasOne')->visible();
            $type->hasMany('visibleHasMany')->visible();
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody(), true);
        $attributes = $document['data']['attributes'] ?? [];
        $relationships = $document['data']['relationships'] ?? [];

        $this->assertArrayHasKey('visibleAttribute', $attributes);
        $this->assertArrayHasKey('visibleHasOne', $relationships);
        $this->assertArrayHasKey('visibleHasMany', $relationships);
    }

    public function test_attributes_can_be_conditionally_visible()
    {
        $this->markTestIncomplete();

        $this->api->resource('users', new MockAdapter, function (Type $type) {
            $type->attribute('visibleAttribute')
                ->visible(function () { return true; });

            $type->attribute('hiddenAttribute')
                ->visible(function () { return false; });

            $type->hasOne('visibleHasOne')
                ->visible(function () { return true; });

            $type->hasOne('hiddenHasOne')
                ->visible(function () { return false; });

            $type->hasMany('visibleHasMany')
                ->visible(function () { return true; });

            $type->hasMany('hiddenHasMany')
                ->visible(function () { return false; });
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody(), true);
        $attributes = $document['data']['attributes'] ?? [];
        $relationships = $document['data']['relationships'] ?? [];

        $this->assertArrayHasKey('visibleAttribute', $attributes);
        $this->assertArrayHasKey('visibleHasOne', $relationships);
        $this->assertArrayHasKey('visibleHasMany', $relationships);

        $this->assertArrayNotHasKey('hiddenAttribute', $attributes);
        $this->assertArrayNotHasKey('hiddenHasOne', $relationships);
        $this->assertArrayNotHasKey('hiddenHasMany', $relationships);
    }

    public function test_attribute_visible_callback_receives_correct_parameters()
    {
        $this->markTestIncomplete();

        $called = 0;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $callback = function ($model, $request) use (&$called) {
                $this->assertSame($this->adapter->models['1'], $model);
                $this->assertInstanceOf(RequestInterface::class, $request);
                $called++;
            };

            $type->attribute('attribute')
                ->visible($callback);

            $type->hasOne('hasOne')
                ->visible($callback);

            $type->hasMany('hasMany')
                ->visible($callback);
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertEquals(3, $called);
    }

    public function test_attributes_can_be_explicitly_hidden()
    {
        $this->markTestIncomplete();

        $this->api->resource('users', new MockAdapter, function (Type $type) {
            $type->attribute('hiddenAttribute')->hidden();
            $type->hasOne('hiddenHasOne')->hidden();
            $type->hasMany('hiddenHasMany')->hidden();
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody(), true);
        $attributes = $document['data']['attributes'] ?? [];
        $relationships = $document['data']['relationships'] ?? [];

        $this->assertArrayNotHasKey('hiddenAttribute', $attributes);
        $this->assertArrayNotHasKey('hiddenHasOne', $relationships);
        $this->assertArrayNotHasKey('hiddenHasMany', $relationships);
    }

    public function test_attributes_can_be_conditionally_hidden()
    {
        $this->markTestIncomplete();

        $this->api->resource('users', new MockAdapter, function (Type $type) {
            $type->attribute('visibleAttribute')
                ->hidden(function () { return false; });

            $type->attribute('hiddenAttribute')
                ->hidden(function () { return true; });

            $type->hasOne('visibleHasOne')
                ->hidden(function () { return false; });

            $type->hasOne('hiddenHasOne')
                ->hidden(function () { return true; });

            $type->hasMany('visibleHasMany')
                ->hidden(function () { return false; });

            $type->hasMany('hiddenHasMany')
                ->hidden(function () { return true; });
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody(), true);
        $attributes = $document['data']['attributes'] ?? [];
        $relationships = $document['data']['relationships'] ?? [];

        $this->assertArrayHasKey('visibleAttribute', $attributes);
        $this->assertArrayHasKey('visibleHasOne', $relationships);
        $this->assertArrayHasKey('visibleHasMany', $relationships);

        $this->assertArrayNotHasKey('hiddenAttribute', $attributes);
        $this->assertArrayNotHasKey('hiddenHasOne', $relationships);
        $this->assertArrayNotHasKey('hiddenHasMany', $relationships);
    }

    public function test_attribute_hidden_callback_receives_correct_parameters()
    {
        $this->markTestIncomplete();

        $called = 0;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $callback = function ($model, $request) use (&$called) {
                $this->assertSame($this->adapter->models['1'], $model);
                $this->assertInstanceOf(RequestInterface::class, $request);
                $called++;
            };

            $type->attribute('attribute')
                ->hidden($callback);

            $type->hasOne('hasOne')
                ->hidden($callback);

            $type->hasMany('hasMany')
                ->hidden($callback);
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertEquals(3, $called);
    }

    // to_one, to_many...
}
