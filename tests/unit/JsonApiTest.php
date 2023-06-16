<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use Exception;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockException;

class JsonApiTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_error_converts_error_provider_to_json_api_response()
    {
        $response = $this->api->error(new MockException());

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset(
            [
                'errors' => [
                    [
                        'title' => 'Mock Error',
                        'status' => '400',
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_error_converts_non_error_provider_to_internal_server_error()
    {
        $response = $this->api->error(new Exception());

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset(
            [
                'errors' => [
                    [
                        'title' => 'Internal Server Error',
                        'status' => '500',
                    ],
                ],
            ],
            $response->getBody(),
        );
    }
}
