<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Endpoint\CollectionAction;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\Endpoint\ResourceAction;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Header;
use Tobyz\JsonApiServer\Schema\Type\Integer;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class EndpointResponseTest extends AbstractTestCase
{
    public static function endpointProvider(): array
    {
        return [
            'show' => [fn() => Show::make(), 'GET', '/users/1', null],
            'index' => [fn() => Index::make(), 'GET', '/users', null],
            'create' => [fn() => Create::make(), 'POST', '/users', ['data' => ['type' => 'users']]],
            'update' => [
                fn() => Update::make(),
                'PATCH',
                '/users/1',
                ['data' => ['type' => 'users', 'id' => '1']],
            ],
            'delete' => [fn() => Delete::make(), 'DELETE', '/users/1', null],
            'resourceAction' => [
                fn() => ResourceAction::make('test', fn() => null),
                'POST',
                '/users/1/test',
                null,
            ],
            'collectionAction' => [
                fn() => CollectionAction::make('test', fn() => null),
                'POST',
                '/users/test',
                null,
            ],
        ];
    }

    #[DataProvider('endpointProvider')]
    public function test_headers(
        callable $endpointFactory,
        string $method,
        string $uri,
        ?array $body,
    ) {
        $api = new JsonApi();

        $endpoint = $endpointFactory()->headers([
            Header::make('X-Custom-Header')
                ->type(Integer::make())
                ->get(fn() => 42),
        ]);

        $api->resource(
            new MockResource('users', models: [(object) ['id' => '1']], endpoints: [$endpoint]),
        );

        $response = $api->handle($this->buildRequest($method, $uri)->withParsedBody($body));

        $this->assertEquals('42', $response->getHeaderLine('X-Custom-Header'));
    }

    #[DataProvider('endpointProvider')]
    public function test_response_callback(
        callable $endpointFactory,
        string $method,
        string $uri,
        ?array $body,
    ) {
        $api = new JsonApi();

        $endpoint = $endpointFactory()->response(
            fn($response) => $response->withHeader('X-Callback', 'executed'),
        );

        $api->resource(
            new MockResource('users', models: [(object) ['id' => '1']], endpoints: [$endpoint]),
        );

        $response = $api->handle($this->buildRequest($method, $uri)->withParsedBody($body));

        $this->assertEquals('executed', $response->getHeaderLine('X-Callback'));
    }
}
