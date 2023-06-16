<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Delete;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#crud-deleting
 */
class DeletingResourcesTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Delete::make()],
            ),
        );
    }

    public function test_no_content_response_if_resource_successfully_deleted()
    {
        $response = $this->api->handle($this->buildRequest('DELETE', '/users/1'));

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getBody()->getContents());
    }

    // public function test_ok_response_if_meta()
    // {
    //     $this->api->resourceType('users', new MockResource(), function (Type $type) {
    //         $type->deletable();
    //         $type->deleting(function ($model, Context $context) {
    //             $context->meta('foo', 'bar');
    //         });
    //     });
    //
    //     $response = $this->api->handle($this->buildRequest('DELETE', '/users/1'));
    //
    //     $this->assertEquals(200, $response->getStatusCode());
    //     $this->assertJsonApiDocumentSubset(['meta' => ['foo' => 'bar']], $response->getBody());
    // }

    public function test_not_found_error_if_resource_does_not_exist()
    {
        $this->expectException(ResourceNotFoundException::class);

        $this->api->handle($this->buildRequest('DELETE', '/users/404'));
    }
}
