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

use Tobyz\Tests\JsonApiServer\AbstractTestCase;

class RelationshipInclusionTest extends AbstractTestCase
{
    public function setUp(): void
    {

    }

    public function test_to_one_relationships_are_not_includable_by_default()
    {
        $this->markTestIncomplete();
    }

    public function test_to_one_relationships_can_be_made_includable()
    {
        $this->markTestIncomplete();
    }

    public function test_to_one_relationships_can_be_made_not_includable()
    {
        $this->markTestIncomplete();
    }

    public function test_included_to_one_relationships_are_preloaded_via_the_adapter()
    {
        $this->markTestIncomplete();
    }

    public function test_to_one_relationships_can_be_not_loadable()
    {
        $this->markTestIncomplete();
    }

    // to_many...

    public function test_multiple_relationships_can_be_included()
    {
        $this->markTestIncomplete();
    }

    public function test_nested_relationships_can_be_included()
    {
        $this->markTestIncomplete();
    }

    public function test_nested_relationships_include_intermediate_resources()
    {
        $this->markTestIncomplete();
    }

    public function test_relationships_can_be_included_on_single_resource_requests()
    {
        $this->markTestIncomplete();
    }

    public function test_relationships_can_be_included_on_resource_listing_requests()
    {
        $this->markTestIncomplete();
    }

    public function test_relationships_can_be_included_on_create_requests()
    {
        $this->markTestIncomplete();
    }

    public function test_relationships_can_be_included_on_update_requests()
    {
        $this->markTestIncomplete();
    }
}
