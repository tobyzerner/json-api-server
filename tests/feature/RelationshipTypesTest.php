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

class RelationshipTypesTest extends AbstractTestCase
{
    public function setUp(): void
    {

    }

    public function test_to_one_relationship_type_is_inferred_from_relationship_name()
    {
        $this->markTestIncomplete();
    }

    public function test_to_one_relationships_can_specify_a_type()
    {
        $this->markTestIncomplete();
    }

    public function test_to_one_relationships_can_be_polymorphic()
    {
        $this->markTestIncomplete();
    }

    public function test_nested_includes_cannot_be_requested_on_polymorphic_to_one_relationships()
    {
        $this->markTestIncomplete();
    }

    public function test_polymorphic_create_update()
    {
        $this->markTestIncomplete();
    }

    // to_many...
}
