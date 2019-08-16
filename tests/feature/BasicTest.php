<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class BasicTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    public function setUp(): void
    {
        $this->api = new JsonApi('http://example.com');

        $adapter = new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'name' => 'Toby',
            ],
            '2' => (object) [
                'id' => '2',
                'name' => 'Franz',
            ],
        ]);

        $this->api->resource('users', $adapter, function (Type $type) {
            $type->attribute('name')->writable();
            $type->creatable();
            $type->updatable();
            $type->deletable();
        });
    }

    public function test_show_resource()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertJsonApiDocumentSubset([
            'data' => [
                'type' => 'users',
                'id' => '1',
                'attributes' => [
                    'name' => 'Toby'
                ],
                'links' => [
                    'self' => 'http://example.com/users/1'
                ]
            ]
        ], $response->getBody());
    }

    public function test_list_resources()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users')
        );

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertJsonApiDocumentSubset([
            'data' => [
                [
                    'type' => 'users',
                    'id' => '1',
                    'attributes' => [
                        'name' => 'Toby'
                    ],
                    'links' => [
                        'self' => 'http://example.com/users/1'
                    ]
                ],
                [
                    'type' => 'users',
                    'id' => '2',
                    'attributes' => [
                        'name' => 'Franz'
                    ],
                    'links' => [
                        'self' => 'http://example.com/users/2'
                    ]
                ]
            ]
        ], $response->getBody());
    }

    public function test_create_resource()
    {
        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'name' => 'Bob',
                        ],
                    ],
                ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $this->assertJsonApiDocumentSubset([
            'data' => [
                'type' => 'users',
                'id' => '1',
                'attributes' => [
                    'name' => 'Bob',
                ],
                'links' => [
                    'self' => 'http://example.com/users/1',
                ],
            ],
        ], $response->getBody());
    }

    public function test_update_resource()
    {
        $response = $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')
                ->withParsedBody([
                    'data' => [
                        'type' => 'users',
                        'id' => '1',
                        'attributes' => [
                            'name' => 'Bob',
                        ],
                    ],
                ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertJsonApiDocumentSubset([
            'data' => [
                'type' => 'users',
                'id' => '1',
                'attributes' => [
                    'name' => 'Bob',
                ],
                'links' => [
                    'self' => 'http://example.com/users/1',
                ],
            ],
        ], $response->getBody());
    }

    public function test_delete_resource()
    {
        $response = $this->api->handle(
            $this->buildRequest('DELETE', '/users/1')
        );

        $this->assertEquals(204, $response->getStatusCode());
    }
}
