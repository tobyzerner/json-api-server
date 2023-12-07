<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\Number;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class NumberTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_serializes_value_to_number()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'count' => '1.23']],
                endpoints: [Show::make()],
                fields: [Attribute::make('count')->type(Number::make())],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['count' => 1.23]]],
            $response->getBody(),
            true,
        );
    }

    public function test_validates_integer()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('count')
                        ->type(Number::make())
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['count' => 'hello']],
            ]),
        );
    }
}
