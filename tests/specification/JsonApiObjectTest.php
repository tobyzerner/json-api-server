<?php

namespace Tobyz\Tests\JsonApiServer\specification;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
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

    public function test_document_includes_jsonapi_member_with_meta()
    {
        $this->api->meta([
            Attribute::make('implementation')->get(fn() => 'json-api-server'),
            Attribute::make('version')->get(fn() => '1.0.0'),
        ]);

        $response = $this->api->handle($this->buildRequest('GET', '/articles'));

        $this->assertJsonApiDocumentSubset(
            [
                'jsonapi' => [
                    'version' => '1.1',
                    'meta' => [
                        'implementation' => 'json-api-server',
                        'version' => '1.0.0',
                    ],
                ],
            ],
            $response->getBody(),
        );
    }
}
