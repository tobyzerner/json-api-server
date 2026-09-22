<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\CollectionAction;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class CollectionActionTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_collection_action()
    {
        $called = false;

        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [
                    CollectionAction::make('test', function () use (&$called) {
                        $called = true;
                    })->method('POST'),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('POST', '/users/test'));

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertTrue($called);
    }

    public function test_shared_parameters_reach_visibility_and_handler(): void
    {
        $this->api->parameters([
            Parameter::make('locale')
                ->default('EN')
                ->deserialize(fn($value) => strtolower($value)),
        ]);
        $this->api->resource(new MockResource('users', endpoints: [
            CollectionAction::make('test', fn(Context $context) => $context->createResponse([
                'meta' => ['locale' => $context->parameter('locale')],
            ]))->visible(fn(Context $context) => $context->parameter('locale') === 'en'),
        ]));

        $response = $this->api->handle($this->buildRequest('POST', '/users/test'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('en', json_decode($response->getBody(), true)['meta']['locale']);

        $definition = (new OpenApiGenerator())->generate($this->api);
        $parameters = $definition['paths']['/users/test']['post']['parameters'];
        $this->assertCount(1, $parameters);
        $this->assertSame('locale', $parameters[0]['name']);
        $this->assertSame('query', $parameters[0]['in']);
    }
}
