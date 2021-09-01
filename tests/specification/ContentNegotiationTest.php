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

use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.1/#content-negotiation
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
        $this->api->resourceType('users', new MockAdapter());
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

    public function test_success_when_request_content_type_contains_profile()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
                ->withHeader('Accept', 'application/vnd.api+json; profile="http://example.com/profile"')
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_error_when_request_content_type_contains_unknown_parameter()
    {
        $request = $this->buildRequest('PATCH', '/users/1')
            ->withHeader('Content-Type', 'application/vnd.api+json; unknown="parameter"');

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->api->handle($request);
    }

    public function test_error_when_request_content_type_contains_unsupported_extension()
    {
        $request = $this->buildRequest('PATCH', '/users/1')
            ->withHeader('Content-Type', 'application/vnd.api+json; ext="http://example.com/extension"');

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->api->handle($request);
    }

    public function test_success_when_accepts_wildcard()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
                ->withHeader('Accept', '*/*')
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_error_when_all_accepts_have_unknown_parameters()
    {
        $request = $this->buildRequest('GET', '/users/1')
            ->withHeader('Accept', 'application/vnd.api+json; unknown="parameter", application/vnd.api+json; unknown="parameter2"');

        $this->expectException(NotAcceptableException::class);

        $this->api->handle($request);
    }

    public function test_success_when_only_some_accepts_have_parameters()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
                ->withHeader('Accept', 'application/vnd.api+json; unknown="parameter", application/vnd.api+json')
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_responds_with_vary_header()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertEquals('Accept', $response->getHeaderLine('vary'));
    }
}
