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

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class ContentNegotiationTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');
        $this->api->resource('users', new MockAdapter(), function (Type $type) {
            // no fields
        });
    }

    public function testJsonApiContentTypeIsReturned()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertEquals(
            'application/vnd.api+json',
            $response->getHeaderLine('Content-Type')
        );
    }

    public function testErrorWhenValidRequestContentTypeHasParameters()
    {
        $request = $this->buildRequest('PATCH', '/users/1')
            ->withHeader('Content-Type', 'application/vnd.api+json;profile="http://example.com/last-modified"');

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->api->handle($request);
    }

    public function testErrorWhenAllValidAcceptsHaveParameters()
    {
        $request = $this->buildRequest('GET', '/users/1')
            ->withHeader('Accept', 'application/vnd.api+json;profile="http://example.com/last-modified", application/vnd.api+json;profile="http://example.com/versioning"');

        $this->expectException(NotAcceptableException::class);

        $this->api->handle($request);
    }

    public function testSuccessWhenOnlySomeAcceptsHaveParameters()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
                ->withHeader('Accept', 'application/vnd.api+json;profile="http://example.com/last-modified", application/vnd.api+json')
        );

        $this->assertEquals(200, $response->getStatusCode());
    }
}
