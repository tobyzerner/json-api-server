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
    public function test_contains_on_exact_match()
    {
        $header = new MediaTypes('application/json');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }

    public function test_contains_does_not_match_with_extra_parameters()
    {
        $header = new MediaTypes('application/json; profile=foo');

        $this->assertFalse(
            $header->containsExactly('application/json')
        );
    }

    public function test_contains_matches_when_only_weight_is_provided()
    {
        $header = new MediaTypes('application/json; q=0.8');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }

    public function test_contains_does_not_match_with_extra_parameters_before_weight()
    {
        $header = new MediaTypes('application/json; profile=foo; q=0.8');

        $this->assertFalse(
            $header->containsExactly('application/json')
        );
    }

    public function test_contains_matches_with_extra_parameters_after_weight()
    {
        $header = new MediaTypes('application/json; q=0.8; profile=foo');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }

    public function test_contains_matches_when_one_of_multiple_media_types_is_valid()
    {
        $header = new MediaTypes('application/json; profile=foo, application/json; q=0.6');

        $this->assertTrue(
            $header->containsExactly('application/json')
        );
    }
}
