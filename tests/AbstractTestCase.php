<?php

/*
 * This file is part of JSON-API.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\Tests\JsonApiServer;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Uri;

abstract class AbstractTestCase extends TestCase
{
    use ArraySubsetAsserts;

    protected function assertJsonApiDocumentSubset($subset, string $body, bool $checkForObjectIdentity = false, string $message = ''): void
    {
        static::assertArraySubset($subset, json_decode($body, true), $checkForObjectIdentity, $message);
    }

    protected function buildRequest(string $method, string $uri): ServerRequest
    {
        return (new ServerRequest)
            ->withMethod($method)
            ->withUri(new Uri($uri));
    }
}
