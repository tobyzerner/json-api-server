<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\unit\Schema;

use PHPUnit\Framework\TestCase;
use Tobyz\JsonApiServer\Schema\Type;

class TypeTest extends TestCase
{
    public function test_returns_an_existing_field_with_the_same_name_of_the_same_type()
    {
        $type = new Type;

        $attribute = $type->attribute('dogs');
        $attributeAgain = $type->attribute('dogs');
        $fields = $type->getFields();

        $this->assertSame($attribute, $attributeAgain);
        $this->assertEquals(1, count($fields));
    }

    public function test_overwrites_an_existing_field_with_the_same_name_of_a_different_type()
    {
        $type = new Type;

        $attribute = $type->attribute('dogs');
        $hasOne = $type->hasOne('dogs');
        $fields = $type->getFields();

        $this->assertNotSame($attribute, $hasOne);
        $this->assertEquals(1, count($fields));
        $this->assertSame($hasOne, $fields['dogs']);
    }
}
