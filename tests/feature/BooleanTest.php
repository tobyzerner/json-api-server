<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\Boolean;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class BooleanTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_serializes_value_to_boolean()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'name' => 'hello']],
                endpoints: [Show::make()],
                fields: [Attribute::make('name')->type(Boolean::make())],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['name' => true]]],
            $response->getBody(),
            true,
        );
    }

    public function test_validates_boolean()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('name')
                        ->type(Boolean::make())
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['name' => 'hello']],
            ]),
        );
    }
}
