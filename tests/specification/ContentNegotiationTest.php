<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobscure\Tests\JsonApiServer\specification;

use Tobscure\JsonApiServer\Api;
use Tobscure\JsonApiServer\Exception\NotAcceptableException;
use Tobscure\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobscure\JsonApiServer\Schema\Builder;
use Tobscure\Tests\JsonApiServer\AbstractTestCase;
use Tobscure\Tests\JsonApiServer\MockAdapter;

class ContentNegotiationTest extends AbstractTestCase
{
    /**
     * @var Api
     */
    private $api;

    public function setUp()
    {
        $this->api = new Api('http://example.com');
        $this->api->resource('users', new MockAdapter(), function (Builder $schema) {
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
