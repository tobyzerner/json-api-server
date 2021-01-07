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

use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class CreateTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('/');
    }

    protected function createResource(array $data = [])
    {
        return $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => array_merge([
                        'type' => 'users',
                        'id' => '1',
                    ], $data)
                ])
        );
    }

    public function test_resources_are_not_creatable_by_default()
    {
        $this->api->resource('users', new MockAdapter());

        $this->expectException(ForbiddenException::class);

        $this->createResource();
    }

    public function test_resource_creation_can_be_explicitly_enabled()
    {
        $this->api->resource('users', new MockAdapter(), function (Type $type) {
            $type->creatable();
        });

        $response = $this->createResource();

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_resource_creation_can_be_conditionally_enabled()
    {
        $this->api->resource('users', new MockAdapter(), function (Type $type) {
            $type->creatable(function () {
                return true;
            });
        });

        $response = $this->createResource();

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function test_resource_creation_can_be_explicitly_disabled()
    {
        $this->api->resource('users', new MockAdapter(), function (Type $type) {
            $type->notCreatable();
        });

        $this->expectException(ForbiddenException::class);

        $this->createResource();
    }

    public function test_resource_creation_can_be_conditionally_disabled()
    {
        $this->api->resource('users', new MockAdapter(), function (Type $type) {
            $type->creatable(function () {
                return false;
            });
        });

        $this->expectException(ForbiddenException::class);

        $this->createResource();
    }

    public function test_resource_creatable_callback_receives_correct_parameters()
    {
        $called = false;

        $this->api->resource('users', new MockAdapter(), function (Type $type) use (&$called) {
            $type->creatable(function ($context) use (&$called) {
                $this->assertInstanceOf(Context::class, $context);
                return $called = true;
            });
        });

        $this->createResource();

        $this->assertTrue($called);
    }

    public function test_new_models_are_supplied_and_saved_by_the_adapter()
    {
        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newModel()->willReturn($createdModel = (object) []);
        $adapter->save($createdModel)->shouldBeCalled();
        $adapter->getId($createdModel)->willReturn('1');

        $this->api->resource('users', $adapter->reveal(), function (Type $type) {
            $type->creatable();
        });

        $this->createResource();
    }

    public function test_resources_can_provide_custom_models()
    {
        $createdModel = (object) [];

        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newModel()->shouldNotBeCalled();
        $adapter->save($createdModel)->shouldBeCalled();
        $adapter->getId($createdModel)->willReturn('1');

        $this->api->resource('users', $adapter->reveal(), function (Type $type) use ($createdModel) {
            $type->creatable();
            $type->newModel(function ($context) use ($createdModel) {
                $this->assertInstanceOf(Context::class, $context);
                return $createdModel;
            });
        });

        $this->createResource();
    }

    public function test_resources_can_provide_custom_savers()
    {
        $called = false;

        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newModel()->willReturn($createdModel = (object) []);
        $adapter->save($createdModel)->shouldNotBeCalled();
        $adapter->getId($createdModel)->willReturn('1');

        $this->api->resource('users', $adapter->reveal(), function (Type $type) use ($createdModel, &$called) {
            $type->creatable();
            $type->save(function ($model, $context) use ($createdModel, &$called) {
                $model->id = '1';
                $this->assertSame($createdModel, $model);
                $this->assertInstanceOf(Context::class, $context);
                return $called = true;
            });
        });

        $this->createResource();

        $this->assertTrue($called);
    }

    public function test_resources_can_have_creation_listeners()
    {
        $called = 0;

        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newModel()->willReturn($createdModel = (object) []);
        $adapter->getId($createdModel)->willReturn('1');

        $this->api->resource('users', $adapter->reveal(), function (Type $type) use ($adapter, $createdModel, &$called) {
            $type->creatable();
            $type->onCreating(function ($model, $context) use ($adapter, $createdModel, &$called) {
                $this->assertSame($createdModel, $model);
                $this->assertInstanceOf(Context::class, $context);
                $adapter->save($createdModel)->shouldNotHaveBeenCalled();
                $called++;
            });
            $type->onCreated(function ($model, $context) use ($adapter, $createdModel, &$called) {
                $this->assertSame($createdModel, $model);
                $this->assertInstanceOf(Context::class, $context);
                $adapter->save($createdModel)->shouldHaveBeenCalled();
                $called++;
            });
        });

        $this->createResource();

        $this->assertEquals(2, $called);
    }
}
