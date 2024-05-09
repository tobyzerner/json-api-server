<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Index;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiGenerator;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class OpenApiTest extends AbstractTestCase
{
    public function test_generates_openapi_spec()
    {
        $api = new JsonApi();

        $api->resource(
            new MockResource(
                'users',
                endpoints: [Index::make()],
                fields: [],
                meta: [],
                filters: [],
                sorts: [],
            ),
        );

        $generator = new OpenApiGenerator();

        print_r($generator->generate($api));
    }
}
