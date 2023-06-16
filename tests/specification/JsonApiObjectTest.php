<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

/**
 * @see https://jsonapi.org/format/1.1/#document-jsonapi-object
 */
class JsonApiObjectTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(new MockResource('articles', endpoints: [Index::make()]));
    }

    public function test_document_includes_jsonapi_member_with_version_1_1()
    {
        $response = $this->api->handle($this->buildRequest('GET', '/articles'));

        $this->assertJsonApiDocumentSubset(
            [
                'jsonapi' => [
                    'version' => '1.1',
                ],
            ],
            $response->getBody(),
        );
    }
}
