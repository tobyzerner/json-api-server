<?php

namespace Tobyz\Tests\JsonApiServer;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    use ArraySubsetAsserts;

    protected function assertJsonApiDocumentSubset(
        $subset,
        string $body,
        bool $checkForObjectIdentity = false,
        string $message = '',
    ): void {
        static::assertArraySubset(
            $subset,
            json_decode($body, true),
            $checkForObjectIdentity,
            $message,
        );
    }

    protected function buildRequest(string $method, string $uri): ServerRequest
    {
        return new ServerRequest($method, $uri);
    }
}
