<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class StrTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_serializes_value_to_string()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'name' => 1]],
                endpoints: [Show::make()],
                fields: [Attribute::make('name')->type(Str::make())],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['name' => '1']]],
            $response->getBody(),
            true,
        );
    }

    public function test_validates_string()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('name')
                        ->type(Str::make())
                        ->writable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['name' => 1]],
            ]),
        );
    }

    public function test_invalid_enum()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('type')
                        ->type(Str::make()->enum(['A', 'B']))
                        ->writable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['type' => 'C']],
            ]),
        );
    }

    public function test_valid_enum()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('type')
                        ->type(Str::make()->enum(['A', 'B']))
                        ->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['type' => 'A']],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['type' => 'A']]],
            $response->getBody(),
            true,
        );
    }
}
