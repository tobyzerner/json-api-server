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

class CountabilityTest extends AbstractTestCase
{
    /**
     * @var JsonApi
     */
    private $api;

    /**
     * @var MockAdapter
     */
    private $adapter;

    public function setUp(): void
    {
        $this->api = new JsonApi('/');

        $models = [];
        for ($i = 1; $i <= 100; $i++) {
            $models[] = (object) ['type' => 'users', 'id' => $i];
        }

        $this->adapter = new MockAdapter($models, 'users');
    }

    public function test_total_number_of_resources_and_last_pagination_link_is_included_by_default()
    {
        $this->api->resourceType('users', $this->adapter);

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users')
        );

        $document = json_decode($response->getBody(), true);

        $this->assertArrayHasKey('last', $document['links'] ?? []);
        $this->assertEquals(100, $document['meta']['total'] ?? null);
    }

    public function test_types_can_be_made_uncountable()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->uncountable();
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users')
        );

        $document = json_decode($response->getBody(), true);

        $this->assertArrayNotHasKey('last', $document['links'] ?? []);
        $this->assertArrayNotHasKey('total', $document['meta'] ?? []);
    }

    public function test_types_can_be_made_countable()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->uncountable();
            $type->countable();
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users')
        );

        $document = json_decode($response->getBody(), true);

        $this->assertArrayHasKey('last', $document['links'] ?? []);
        $this->assertEquals(100, $document['meta']['total'] ?? null);
    }
}
