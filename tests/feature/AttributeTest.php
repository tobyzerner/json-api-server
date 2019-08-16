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

use JsonApiPhp\JsonApi\ErrorDocument;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\ErrorProviderInterface;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class AttributeTest extends AbstractTestCase
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
            '1' => (object) ['id' => '1', 'name' => 'Toby', 'color' => 'yellow'],
            '2' => (object) ['id' => '2', 'name' => 'Franz', 'color' => 'blue'],
        ]);
    }

    public function test_multiple_attributes()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->attribute('name');
            $type->attribute('color');
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertJsonApiDocumentSubset([
            'data' => [
                'attributes' => [
                    'name' => 'Toby',
                    'color' => 'yellow',
                ],
            ]
        ], $response->getBody());
    }

    public function test_attributes_can_specify_a_property()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->attribute('name')
                ->property('color');
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertJsonApiDocumentSubset([
            'data' => [
                'attributes' => [
                    'name' => 'yellow',
                ],
            ]
        ], $response->getBody());
    }

    public function test_attributes_can_have_getters()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->attribute('name')
                ->get('Toby');

            $type->attribute('color')
                ->get(function ($model, $request) use (&$called) {
                    $called = true;

                    $this->assertSame($this->adapter->models['1'], $model);
                    $this->assertInstanceOf(RequestInterface::class, $request);

                    return 'yellow';
                });
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertTrue($called);

        $this->assertJsonApiDocumentSubset([
            'data' => [
                'attributes' => [
                    'name' => 'Toby',
                    'color' => 'yellow',
                ],
            ]
        ], $response->getBody());
    }

    public function test_attribute_setter_receives_correct_parameters()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->updatable();
            $type->attribute('writable')
                ->writable()
                ->set(function ($model, $value, $request) use (&$called) {
                    $this->assertSame($this->adapter->models['1'], $model);
                    $this->assertEquals('value', $value);
                    $this->assertInstanceOf(RequestInterface::class, $request);

                    $called = true;
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

    public function test_attribute_setter_precludes_adapter_action()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->updatable();
            $type->attribute('writable')
                ->writable()
                ->set(function () {});
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

        $this->assertTrue(empty($this->adapter->models['1']->writable));
    }

    public function test_attribute_saver_receives_correct_parameters()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->updatable();
            $type->attribute('writable')
                ->writable()
                ->save(function ($model, $value, $request) use (&$called) {
                    $this->assertSame($this->adapter->models['1'], $model);
                    $this->assertEquals('value', $value);
                    $this->assertInstanceOf(RequestInterface::class, $request);

                    $called = true;
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

    public function test_attribute_saver_precludes_adapter_action()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->updatable();
            $type->attribute('writable')
                ->writable()
                ->save(function () {});
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

        $this->assertTrue(empty($this->adapter->models['1']->writable));
    }

    public function test_attributes_can_run_callback_after_being_saved()
    {
        $called = false;

        $this->api->resource('users', $this->adapter, function (Type $type) use (&$called) {
            $type->updatable();
            $type->attribute('writable')
                ->writable()
                ->saved(function ($model, $value, $request) use (&$called) {
                    $this->assertTrue($this->adapter->models['1']->saveWasCalled);
                    $this->assertSame($this->adapter->models['1'], $model);
                    $this->assertEquals('value', $value);
                    $this->assertInstanceOf(RequestInterface::class, $request);

                    $called = true;
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

    public function test_attributes_can_have_default_values()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->creatable();

            $type->attribute('name')
                ->default('Toby');

            $type->attribute('color')
                ->default(function () {
                    return 'yellow';
                });
        });

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                    ]
                ])
        );

        $this->assertEquals('Toby', $this->adapter->createdModel->name);
        $this->assertEquals('yellow', $this->adapter->createdModel->color);
    }

    public function test_attribute_default_callback_receives_correct_parameters()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->creatable();
            $type->attribute('attribute')
                ->default(function ($request) {
                    $this->assertInstanceOf(RequestInterface::class, $request);
                });
        });

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                    ]
                ])
        );
    }

    public function test_attribute_values_from_request_override_default_values()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->creatable();
            $type->attribute('name')
                ->writable()
                ->default('Toby');
        });

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'name' => 'Franz',
                        ]
                    ]
                ])
        );

        $this->assertEquals('Franz', $this->adapter->createdModel->name);
    }

    public function test_attributes_can_be_validated()
    {
        $this->api->resource('users', $this->adapter, function (Type $type) {
            $type->creatable();

            $type->attribute('name')
                ->writable()
                ->validate(function ($fail, $value, $model, $request, $field) {
                    $this->assertEquals('Toby', $value);
                    $this->assertSame($this->adapter->createdModel, $model);
                    $this->assertInstanceOf(ServerRequestInterface::class, $request);
                    $this->assertInstanceOf(Attribute::class, $field);

                    $fail('detail');
                });
        });

        $this->expectException(UnprocessableEntityException::class);

        try {
            $this->api->handle(
                $this->buildRequest('POST', '/users')
                    ->withParsedBody([
                        'data' => [
                            'type' => 'users',
                            'attributes' => [
                                'name' => 'Toby',
                            ]
                        ]
                    ])
            );
        } catch (ErrorProviderInterface $e) {
            $document = new ErrorDocument(...$e->getJsonApiErrors());

            $this->assertArraySubset([
                'errors' => [
                    [
                        'status' => '422',
                        'source' => [
                            'pointer' => '/data/attributes/name'
                        ],
                        'detail' => 'detail'
                    ]
                ]
            ], json_decode(json_encode($document), true));

            throw $e;
        }
    }
}
