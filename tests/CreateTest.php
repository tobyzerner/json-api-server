<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Api;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Serializer;
use Tobyz\JsonApiServer\Schema\Builder;
use Psr\Http\Message\ServerRequestInterface as Request;
use JsonApiPhp\JsonApi;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

class CreateTest extends AbstractTestCase
{
    public function testResourceNotCreatableByDefault()
    {
        $api = new Api('http://example.com');

        $api->resource('users', new MockAdapter(), function (Builder $schema) {
            //
        });

        $request = $this->buildRequest('POST', '/users');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You cannot create this resource');

        $api->handle($request);
    }

    public function testCreateResourceValidatesBody()
    {
        $api = new Api('http://example.com');

        $api->resource('users', new MockAdapter(), function (Builder $schema) {
            $schema->creatable();
        });

        $request = $this->buildRequest('POST', '/users');

        $this->expectException(BadRequestException::class);

        $api->handle($request);
    }

    public function testCreateResource()
    {
        $api = new Api('http://example.com');

        $api->resource('users', $adapter = new MockAdapter(), function (Builder $schema) {
            $schema->creatable();

            $schema->attribute('name')->writable();
        });

        $request = $this->buildRequest('POST', '/users')
            ->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'id' => '1',
                    'attributes' => [
                        'name' => 'Toby'
                    ]
                ]
            ]);

        $response = $api->handle($request);

        $this->assertTrue($adapter->createdModel->saveWasCalled);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(
            [
                'type' => 'users',
                'id' => '1',
                'attributes' => [
                    'name' => 'Toby'
                ],
                'links' => [
                    'self' => 'http://example.com/users/1'
                ]
            ],
            json_decode($response->getBody(), true)['data']
        );
    }

    public function testAttributeWritable()
    {
        $request = $this->buildRequest('POST', '/users')
            ->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'id' => '1',
                    'attributes' => [
                        'writable1' => 'value',
                        'writable2' => 'value',
                        'writable3' => 'value',
                    ]
                ]
            ]);

        $api = new Api('http://example.com');

        $api->resource('users', $adapter = new MockAdapter(), function (Builder $schema) use ($adapter, $request) {
            $schema->creatable();

            $schema->attribute('writable1')->writable();

            $schema->attribute('writable2')->writableIf(function ($arg1, $arg2) use ($adapter, $request) {
                $this->assertInstanceOf(Request::class, $arg1);
                $this->assertEquals($adapter->createdModel, $arg2);
                return true;
            });

            $schema->attribute('writable3')->readonlyIf(function ($arg1, $arg2) use ($adapter, $request) {
                $this->assertInstanceOf(Request::class, $arg1);
                $this->assertEquals($adapter->createdModel, $arg2);
                return false;
            });
        });

        $response = $api->handle($request);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testAttributeReadonly()
    {
        $request = $this->buildRequest('POST', '/users')
            ->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'id' => '1',
                    'attributes' => [
                        'readonly' => 'value',
                    ]
                ]
            ]);

        $api = new Api('http://example.com');

        $api->resource('users', $adapter = new MockAdapter(), function (Builder $schema) use ($adapter, $request) {
            $schema->creatable();

            $schema->attribute('readonly')->readonly();
        });

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Field [readonly] is not writable');

        $api->handle($request);
    }

    public function testAttributeDefault()
    {
        $request = $this->buildRequest('POST', '/users')
            ->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'id' => '1',
                    'attributes' => [
                        'attribute3' => 'userValue'
                    ]
                ]
            ]);

        $api = new Api('http://example.com');

        $api->resource('users', $adapter = new MockAdapter(), function (Builder $schema) use ($request) {
            $schema->creatable();

            $schema->attribute('attribute1')->default('defaultValue');
            $schema->attribute('attribute2')->default(function ($arg1) use ($request) {
                $this->assertInstanceOf(Request::class, $arg1);
                return 'defaultValue';
            });
            $schema->attribute('attribute3')->writable()->default('defaultValue');
        });

        $response = $api->handle($request);

        $this->assertEquals(
            [
                'attribute1' => 'defaultValue',
                'attribute2' => 'defaultValue',
                'attribute3' => 'userValue'
            ],
            json_decode($response->getBody(), true)['data']['attributes']
        );
    }
}
