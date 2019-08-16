<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer\unit\Http;

use PHPUnit\Framework\TestCase;
use Tobyz\JsonApiServer\Http\MediaTypes;

class MediaTypesTest extends TestCase
{
    public function testContainsOnExactMatch()
    {
        $header = new MediaTypes('application/json');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }

    public function testContainsDoesNotMatchWithExtraParameters()
    {
        $header = new MediaTypes('application/json; profile=foo');

        $this->assertFalse(
            $header->containsExactly('application/json')
        );
    }

    public function testContainsMatchesWhenOnlyWeightIsProvided()
    {
        $header = new MediaTypes('application/json; q=0.8');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }

    public function testContainsDoesNotMatchWithExtraParametersBeforeWeight()
    {
        $header = new MediaTypes('application/json; profile=foo; q=0.8');

        $this->assertFalse(
            $header->containsExactly('application/json')
        );
    }

    public function testContainsMatchesWithExtraParametersAfterWeight()
    {
        $header = new MediaTypes('application/json; q=0.8; profile=foo');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }

    public function testContainsMatchesWhenOneOfMultipleMediaTypesIsValid()
    {
        $header = new MediaTypes('application/json; profile=foo, application/json; q=0.6');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }
}
