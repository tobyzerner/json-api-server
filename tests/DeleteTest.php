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

class DeleteTest extends AbstractTestCase
{
    public function testResourceNotDeletableByDefault()
    {
        $api = new Api('http://example.com');

        $api->resource('users', new MockAdapter(), function (Builder $schema) {
            //
        });

        $request = $this->buildRequest('DELETE', '/users/1');

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You cannot delete this resource');

        $api->handle($request);
    }

    public function testDeleteResource()
    {
        $usersAdapter = new MockAdapter([
            '1' => $user = (object)['id' => '1']
        ]);

        $api = new Api('http://example.com');

        $api->resource('users', $usersAdapter, function (Builder $schema) {
            $schema->deletable();
        });

        $request = $this->buildRequest('DELETE', '/users/1');
        $response = $api->handle($request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertTrue($user->deleteWasCalled);
    }
}
