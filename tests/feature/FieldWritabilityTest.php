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
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class FieldWritabilityTest extends AbstractTestCase
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

    public function test_attributes_are_readonly_by_default()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->updatable();
            $type->attribute('readonly');
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'readonly' => 'value',
                        ]
                    ]
                ])
        );
    }

    public function test_attributes_can_be_explicitly_writable()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->updatable();
            $type->attribute('writable')->writable();
        });

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'writable' => 'value',
                        ]
                    ]
                ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('value', $this->adapter->models['1']->writable);
    }

    public function test_attributes_can_be_conditionally_writable()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->updatable();
            $type->attribute('writable')
                ->writable(function () { return true; });
        });

        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'writable' => 'value',
                        ]
                    ]
                ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('value', $this->adapter->models['1']->writable);
    }

    public function test_attribute_writable_callback_receives_correct_parameters()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->updatable();
            $type->attribute('writable')
                ->writable(function ($model, $context) use (&$called) {
                    $this->assertSame($this->adapter->models['1'], $model);
                    $this->assertInstanceOf(Context::class, $context);

                    return $called = true;
                });
        });

        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'writable' => 'value',
                        ]
                    ]
                ])
        );

        $this->assertTrue($called);
    }

    public function test_attributes_can_be_explicitly_readonly()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->updatable();
            $type->attribute('readonly')->readonly();
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'readonly' => 'value',
                        ]
                    ]
                ])
        );
    }

    public function test_attributes_can_be_conditionally_readonly()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->updatable();
            $type->attribute('readonly')
                ->readonly(function () { return true; });
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'readonly' => 'value',
                        ]
                    ]
                ])
        );
    }

    public function test_attribute_readonly_callback_receives_correct_parameters()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->updatable();
            $type->attribute('readonly')
                ->readonly(function ($model, $context) use (&$called) {
                    $called = true;

                    $this->assertSame($this->adapter->models['1'], $model);
                    $this->assertInstanceOf(Context::class, $context);

                    return false;
                });
        });

        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'readonly' => 'value',
                        ]
                    ]
                ])
        );

        $this->assertTrue($called);
    }

    public function test_field_is_only_writable_once_on_creation()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->creatable();
            $type->updatable();
            $type->attribute('writableOnce')->writable()->once();
        });

        $payload = [
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'writableOnce' => 'value',
                ]
            ]
        ];

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody($payload)
        );

        $this->assertEquals(201, $response->getStatusCode());

        $this->expectException(BadRequestException::class);

        $payload['data']['id'] = '1';
        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody($payload)
        );
    }
}
