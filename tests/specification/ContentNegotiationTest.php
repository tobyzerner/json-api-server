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

/**
 * @see https://jsonapi.org/format/#content-negotiation
 */
class ContentNegotiationTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');
        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            // no fields
        });
    }

    public function test_json_api_content_type_is_returned()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertEquals(
            'application/vnd.api+json',
            $response->getHeaderLine('Content-Type')
        );
    }

    public function test_error_when_request_content_type_has_parameters()
    {
        $request = $this->buildRequest('PATCH', '/users/1')
            ->withHeader('Content-Type', 'application/vnd.api+json;profile="http://example.com/last-modified"');

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->api->handle($request);
    }

    public function test_error_when_all_accepts_have_parameters()
    {
        $request = $this->buildRequest('GET', '/users/1')
            ->withHeader('Accept', 'application/vnd.api+json;profile="http://example.com/last-modified", application/vnd.api+json;profile="http://example.com/versioning"');

        $this->expectException(NotAcceptableException::class);

        $this->api->handle($request);
    }

    public function test_success_when_only_some_accepts_have_parameters()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
                ->withHeader('Accept', 'application/vnd.api+json;profile="http://example.com/last-modified", application/vnd.api+json')
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_success_when_accepts_wildcard()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
                ->withHeader('Accept', '*/*')
        );

        $this->assertEquals(200, $response->getStatusCode());
    }
}
