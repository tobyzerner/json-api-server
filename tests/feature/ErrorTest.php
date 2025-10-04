<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Exception;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockErrorException;

class ErrorTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_converts_error_provider_to_json_api_response()
    {
        $response = $this->api->error(new MockErrorException());

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

    public function test_converts_non_error_provider_to_internal_server_error()
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

    public function test_error_customization_with_placeholder_replacement()
    {
        $this->api->errors([
            MockErrorException::class => [
                'code' => 'custom_code',
                'title' => 'Custom Title',
                'detail' => 'Custom detail :foo',
                'meta' => ['customMeta' => 'test'],
            ],
        ]);

        $response = $this->api->error((new MockErrorException())->meta(['foo' => 'bar']));

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset(
            [
                'errors' => [
                    [
                        'status' => '400',
                        'code' => 'custom_code',
                        'title' => 'Custom Title',
                        'detail' => 'Custom detail bar',
                        'meta' => [
                            'foo' => 'bar',
                            'customMeta' => 'test',
                        ],
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_multiple_errors_with_customization()
    {
        $this->api->errors([
            MockErrorException::class => [
                'detail' => 'Custom detail :foo',
            ],
        ]);

        $response = $this->api->error(
            new JsonApiErrorsException([
                (new MockErrorException())->meta(['foo' => 'bar']),
                (new MockErrorException())->meta(['foo' => 'baz']),
            ]),
        );

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertJsonApiDocumentSubset(
            [
                'errors' => [
                    [
                        'status' => '400',
                        'code' => 'mock_error',
                        'title' => 'Mock Error',
                        'detail' => 'Custom detail bar',
                    ],
                    [
                        'status' => '400',
                        'code' => 'mock_error',
                        'title' => 'Mock Error',
                        'detail' => 'Custom detail baz',
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_multiple_errors_most_applicable_status()
    {
        $response = $this->api->error(
            new JsonApiErrorsException([
                new MockErrorException('429'),
                new MockErrorException('429'),
            ]),
        );

        $this->assertEquals(429, $response->getStatusCode());

        $response = $this->api->error(
            new JsonApiErrorsException([
                new MockErrorException('429'),
                new MockErrorException('400'),
                new MockErrorException('500'),
            ]),
        );

        $this->assertEquals(400, $response->getStatusCode());

        $response = $this->api->error(
            new JsonApiErrorsException([
                new MockErrorException('501'),
                new MockErrorException('400'),
                new MockErrorException('502'),
            ]),
        );

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_error_id_can_be_set_via_fluent_method()
    {
        $response = $this->api->error((new MockErrorException())->id('error-12345'));

        $this->assertJsonApiDocumentSubset(
            [
                'errors' => [
                    [
                        'id' => 'error-12345',
                        'status' => '400',
                    ],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_error_id_can_be_set_via_customization()
    {
        $this->api->errors([
            MockErrorException::class => [
                'id' => 'custom-error-id',
            ],
        ]);

        $response = $this->api->error(new MockErrorException());

        $this->assertJsonApiDocumentSubset(
            [
                'errors' => [
                    [
                        'id' => 'custom-error-id',
                        'status' => '400',
                    ],
                ],
            ],
            $response->getBody(),
        );
    }
}
