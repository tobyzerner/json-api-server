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

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class FieldGettersTest extends AbstractTestCase
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

        $this->adapter = new MockAdapter([
            '1' => (object) [
                'id' => '1',
                'test' => 'value',
                'animal' => (object) ['id' => '1'],
                'animals' => [
                    (object) ['id' => '1'],
                    (object) ['id' => '2']
                ]
            ]
        ]);
    }

    public function test_attribute_values_are_retrieved_via_the_adapter_by_default()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->attribute('test');
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody());

        $this->assertEquals('value', $document->data->attributes->test ?? null);
    }

    public function test_attribute_getters_allow_a_custom_value_to_be_used()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->attribute('test')
                ->get(function ($model, Context $context) {
                    return 'custom';
                });
        });

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody());

        $this->assertEquals('custom', $document->data->attributes->test ?? null);
    }

    public function test_has_one_values_are_retrieved_via_the_adapter_by_default()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->hasOne('animal')->withLinkage();
        });

        $this->api->resourceType('animals', new MockAdapter());

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody());

        $this->assertEquals('1', $document->data->relationships->animal->data->id ?? null);
    }

    public function test_has_one_getters_allow_a_custom_value_to_be_used()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->hasOne('animal')->withLinkage()
                ->get(function ($model, bool $linkageOnly, Context $context) {
                    return (object) ['id' => '2'];
                });
        });

        $this->api->resourceType('animals', new MockAdapter());

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody());

        $this->assertEquals('2', $document->data->relationships->animal->data->id ?? null);
    }

    public function test_has_many_values_are_retrieved_via_the_adapter_by_default()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->hasMany('animals')->withLinkage();
        });

        $this->api->resourceType('animals', new MockAdapter());

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody());

        $this->assertEquals('1', $document->data->relationships->animals->data[0]->id ?? null);
        $this->assertEquals('2', $document->data->relationships->animals->data[1]->id ?? null);
    }

    public function test_has_many_getters_allow_a_custom_value_to_be_used()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->hasMany('animals')->withLinkage()
                ->get(function ($model, bool $linkageOnly, Context $context) {
                    return [
                        (object) ['id' => '2'],
                        (object) ['id' => '3']
                    ];
                });
        });

        $this->api->resourceType('animals', new MockAdapter());

        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')
        );

        $document = json_decode($response->getBody());

        $this->assertEquals('2', $document->data->relationships->animals->data[0]->id ?? null);
        $this->assertEquals('3', $document->data->relationships->animals->data[1]->id ?? null);
    }
}
