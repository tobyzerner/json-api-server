<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class EndpointParametersTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()->parameters([Parameter::make('testParameter')])],
                fields: [
                    Attribute::make('test')->get(
                        fn($model, $context) => $context->parameter('testParameter'),
                    ),
                ],
            ),
        );
    }

    public function test_endpoint_parameter()
    {
        $response = $this->api->handle(
            $this->buildRequest('GET', '/users/1')->withQueryParams(['testParameter' => 'value']),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['test' => 'value']]],
            $response->getBody(),
        );
    }
}
