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

use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/#query-parameters
 */
class QueryParametersTest extends AbstractTestCase
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

    public function test_bad_request_error_if_unknown_query_parameters()
    {
        $request = $this->buildRequest('GET', '/users/1')
            ->withQueryParams(['unknown' => 'value']);

        $this->expectException(BadRequestException::class);

        $this->api->handle($request);
    }

    public function test_supports_custom_query_parameters()
    {
        $request = $this->buildRequest('GET', '/users/1')
            ->withQueryParams(['camelCase' => 'value']);

        $response = $this->api->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
