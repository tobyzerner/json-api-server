<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#query-parameters
 */
class QueryParametersTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource('users', models: [(object) ['id' => '1']], endpoints: [Show::make()]),
        );
    }

    public function test_bad_request_error_if_unknown_query_parameters()
    {
        $request = $this->buildRequest('GET', '/users/1')->withQueryParams(['unknown' => 'value']);

        $this->expectException(BadRequestException::class);

        $this->api->handle($request);
    }

    public function test_supports_custom_query_parameters()
    {
        $request = $this->buildRequest('GET', '/users/1')->withQueryParams([
            'camelCase' => 'value',
        ]);

        $response = $this->api->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
