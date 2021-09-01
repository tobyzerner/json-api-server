<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\unit;

use Exception;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockException;

class JsonApiTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');
    }

    public function test_error_converts_error_provider_to_json_api_response()
    {
        $response = $this->api->error(
            new MockException()
        );

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset([
            'errors' => [
                [
                    'title' => 'Mock Error',
                    'status' => '400',
                ],
            ],
        ], $response->getBody());
    }

    public function test_error_converts_non_error_provider_to_internal_server_error()
    {
        $response = $this->api->error(
            new Exception()
        );

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset([
            'errors' => [
                [
                    'title' => 'Internal Server Error',
                    'status' => '500',
                ],
            ],
        ], $response->getBody());
    }
}
