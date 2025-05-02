<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\ResourceAction;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class ResourceActionTest extends AbstractTestCase
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
                models: [(object) ['id' => '1']],
                endpoints: [
                    ResourceAction::make('test', function () use (&$called) {
                        $called = true;
                    })->method('POST'),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('POST', '/users/1/test'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['type' => 'users', 'id' => '1']],
            $response->getBody(),
        );

        $this->assertTrue($called);
    }
}
