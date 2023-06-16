<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class EndpointVisibilityTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [
                    Show::make()->visible($this->authorize(...)),
                    Index::make()->visible($this->authorize(...)),
                    Create::make()->visible($this->authorize(...)),
                    Update::make()->visible($this->authorize(...)),
                    Delete::make()->visible($this->authorize(...)),
                ],
            ),
        );
    }

    private function authorize(...$args): bool
    {
        return end($args)->request->getHeaderLine('Token') === '1';
    }

    public static function endpointProvider(): array
    {
        return [
            'show' => ['GET', '/users/1'],
            'index' => ['GET', '/users'],
            'create' => ['POST', '/users', ['data' => ['type' => 'users']]],
            'update' => ['PATCH', '/users/1', ['data' => ['type' => 'users', 'id' => '1']]],
            'delete' => ['DELETE', '/users/1'],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoint_forbidden(string $method, string $uri, array $body = null)
    {
        $this->expectException(ForbiddenException::class);

        $this->api->handle($this->buildRequest($method, $uri)->withParsedBody($body));
    }

    #[DataProvider('endpointProvider')]
    public function test_endpoint_allowed(string $method, string $uri, array $body = null)
    {
        $response = $this->api->handle(
            $this->buildRequest($method, $uri)
                ->withParsedBody($body)
                ->withHeader('Token', '1'),
        );

        $this->assertTrue($response->getStatusCode() >= 200 && $response->getStatusCode() < 300);
    }
}
