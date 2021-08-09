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

use Prophecy\PhpUnit\ProphecyTrait;
use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class DeleteTest extends AbstractTestCase
{
    use ProphecyTrait;

    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');
    }

    protected function deleteResource(array $data = [])
    {
        return $this->api->handle(
            $this->buildRequest('DELETE', '/users/1')
        );
    }

    public function test_resources_are_not_deletable_by_default()
    {
        $this->api->resourceType('users', new MockAdapter());

        $this->expectException(ForbiddenException::class);

        $this->deleteResource();
    }

    public function test_resource_deletion_can_be_explicitly_enabled()
    {
        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->deletable();
        });

        $response = $this->deleteResource();

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function test_resource_deletion_can_be_conditionally_enabled()
    {
        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->deletable(function () {
                return true;
            });
        });

        $response = $this->deleteResource();

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function test_resource_deletion_can_be_explicitly_disabled()
    {
        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->notDeletable();
        });

        $this->expectException(ForbiddenException::class);

        $this->deleteResource();
    }

    public function test_resource_deletion_can_be_conditionally_disabled()
    {
        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->deletable(function () {
                return false;
            });
        });

        $this->expectException(ForbiddenException::class);

        $this->deleteResource();
    }

    public function test_resource_deletable_callback_receives_correct_parameters()
    {
        $called = false;

        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newQuery()->willReturn($query = (object) []);
        $adapter->find($query, '1')->willReturn($deletingModel = (object) []);
        $adapter->delete($deletingModel);

        $this->api->resourceType('users', $adapter->reveal(), function (Type $type) use ($deletingModel, &$called) {
            $type->deletable(function ($model, $context) use ($deletingModel, &$called) {
                $this->assertSame($deletingModel, $model);
                $this->assertInstanceOf(Context::class, $context);
                return $called = true;
            });
        });

        $this->deleteResource();

        $this->assertTrue($called);
    }

    public function test_deleting_a_resource_calls_the_delete_adapter_method()
    {
        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newQuery()->willReturn($query = (object) []);
        $adapter->find($query, '1')->willReturn($model = (object) []);
        $adapter->delete($model)->shouldBeCalled();

        $this->api->resourceType('users', $adapter->reveal(), function (Type $type) {
            $type->deletable();
        });

        $this->deleteResource();
    }

    public function test_resources_can_provide_custom_deleters()
    {
        $called = false;

        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newQuery()->willReturn($query = (object) []);
        $adapter->find($query, '1')->willReturn($deletingModel = (object) []);
        $adapter->delete($deletingModel)->shouldNotBeCalled();

        $this->api->resourceType('users', $adapter->reveal(), function (Type $type) use ($deletingModel, &$called) {
            $type->deletable();
            $type->delete(function ($model, $context) use ($deletingModel, &$called) {
                $this->assertSame($deletingModel, $model);
                $this->assertInstanceOf(Context::class, $context);
                return $called = true;
            });
        });

        $this->deleteResource();

        $this->assertTrue($called);
    }

    public function test_resources_can_have_deletion_listeners()
    {
        $called = 0;

        $adapter = $this->prophesize(AdapterInterface::class);
        $adapter->newQuery()->willReturn($query = (object) []);
        $adapter->find($query, '1')->willReturn($deletingModel = (object) []);
        $adapter->delete($deletingModel)->shouldBeCalled();

        $this->api->resourceType('users', $adapter->reveal(), function (Type $type) use ($adapter, $deletingModel, &$called) {
            $type->deletable();
            $type->deleting(function ($model, $context) use ($adapter, $deletingModel, &$called) {
                $this->assertSame($deletingModel, $model);
                $this->assertInstanceOf(Context::class, $context);
                $adapter->delete($deletingModel)->shouldNotHaveBeenCalled();
                $called++;
            });
            $type->deleted(function ($model, $context) use ($adapter, $deletingModel, &$called) {
                $this->assertSame($deletingModel, $model);
                $this->assertInstanceOf(Context::class, $context);
                $adapter->delete($deletingModel)->shouldHaveBeenCalled();
                $called++;
            });
        });

        $this->deleteResource();

        $this->assertEquals(2, $called);
    }
}
