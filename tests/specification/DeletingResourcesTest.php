<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.1/#crud-deleting
 */
class DeletingResourcesTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');

        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->deletable();
        });
    }

    public function test_no_content_response_if_resource_successfully_deleted()
    {
        $response = $this->api->handle(
            $this->buildRequest('DELETE', '/users/1')
        );

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getBody()->getContents());
    }

    public function test_ok_response_if_meta()
    {
        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->deletable();
            $type->deleting(function ($model, Context $context) {
                $context->meta('foo', 'bar');
            });
        });

        $response = $this->api->handle(
            $this->buildRequest('DELETE', '/users/1')
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    }

    public function test_not_found_error_if_resource_does_not_exist()
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle(
            $this->buildRequest('DELETE', '/users/404')
        );
    }
}
