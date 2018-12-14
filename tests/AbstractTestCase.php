<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobscure\Tests\JsonApiServer;

use PHPUnit\Framework\TestCase;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

abstract class AbstractTestCase extends TestCase
{
    public static function assertEncodesTo(string $expected, $obj, string $message = '')
    {
        self::assertEquals(
            json_decode($expected),
            json_decode(json_encode($obj, JSON_UNESCAPED_SLASHES)),
            $message
        );
    }

    protected function buildRequest(string $method, string $uri): ServerRequest
    {
        return (new ServerRequest())
            ->withMethod($method)
            ->withUri(new Uri($uri));
    }
}
