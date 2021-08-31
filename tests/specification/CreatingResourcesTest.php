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
use Tobyz\JsonApiServer\Exception\ConflictException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

/**
 * @see https://jsonapi.org/format/1.1/#crud-creating
 */
class CreatingResourcesTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');

        $this->api->resourceType('users', new MockAdapter(), function (Type $type) {
            $type->creatable();
            $type->attribute('name')->writable();
            $type->hasOne('pet')->writable();
        });
    }

    public function test_bad_request_error_if_body_does_not_contain_data_type()
    {
        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [],
                ])
        );
    }

    public function test_bad_request_error_if_relationship_does_not_contain_data()
    {
        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'relationships' => [
                            'pet' => [],
                        ],
                    ],
                ])
        );
    }

    public function test_forbidden_error_if_client_generated_id_provided()
    {
        $this->expectException(ForbiddenException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                    ],
                ])
        );
    }

    public function test_created_response_includes_created_data_and_location_header()
    {
        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                    ],
                ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('http://example.com/users/1', $response->getHeaderLine('location'));

        $this->assertJsonApiDocumentSubset([
            'data' => [
                'type' => 'users',
                'id' => '1',
                'links' => [
                    'self' => 'http://example.com/users/1',
                ],
            ],
        ], $response->getBody());
    }

    public function test_not_found_error_if_references_resource_that_does_not_exist()
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'relationships' => [
                            'pet' => [
                                'data' => ['type' => 'pets', 'id' => '1'],
                            ],
                        ],
                    ],
                ])
        );
    }

    public function test_conflict_error_if_type_does_not_match_endpoint()
    {
        $this->expectException(ConflictException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'pets',
                        'id' => '1',
                    ],
                ])
        );
    }
}
