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

use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockAdapter;

class SortingTest extends AbstractTestCase
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
        $this->api = new JsonApi('http://example.com');

        $this->adapter = new MockAdapter();
    }

    public function test_attributes_are_not_sortable_by_default()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->attribute('name');
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['sort' => 'name'])
        );
    }

    public function test_attributes_can_be_sortable_by_their_value()
    {
        $attribute = null;

        $this->api->resourceType('users', $this->adapter, function (Type $type) use (&$attribute) {
            $attribute = $type->attribute('name')->sortable();
        });

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['sort' => 'name'])
        );

        $this->assertContains([$attribute, 'asc'], $this->adapter->query->sort);
    }

    public function test_attribute_sortable_callback_receives_correct_parameters()
    {
        $this->markTestIncomplete();
    }

    public function test_attributes_can_be_explicitly_not_sortable()
    {
        $this->api->resourceType('users', $this->adapter, function (Type $type) {
            $type->attribute('name')->notSortable();
        });

        $this->expectException(BadRequestException::class);

        $this->api->handle(
            $this->buildRequest('GET', '/users')
                ->withQueryParams(['sort' => 'name'])
        );
    }

    public function test_types_can_have_custom_sort_fields()
    {
        $this->markTestIncomplete();
    }

    public function test_types_can_have_a_default_sort()
    {
        $this->markTestIncomplete();
    }

    public function test_multiple_sort_fields_can_be_requested()
    {
        $this->markTestIncomplete();
    }

    public function test_sort_fields_can_be_descending_with_minus_prefix()
    {
        $this->markTestIncomplete();
    }
}
