<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\CollectionAction;
use Tobyz\JsonApiServer\JsonApi;
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
}
