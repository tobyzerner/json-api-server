<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\Arr;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class ArrTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_validates_array()
    {
        $this->api->resource(
            new MockResource(
                'customers',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('featureToggles')
                        ->type(Arr::make())
                        ->writable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/customers')->withParsedBody([
                'data' => ['type' => 'customers', 'attributes' => ['featureToggles' => 1]],
            ]),
        );
    }

    public function test_invalid_min_length()
    {
        $this->api->resource(
            new MockResource(
                'customers',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('featureToggles')
                        ->type(Arr::make()->minItems(1))
                        ->writable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/customers')->withParsedBody([
                'data' => ['type' => 'customers', 'attributes' => ['featureToggles' => []]],
            ]),
        );
    }

    public function test_invalid_max_length()
    {
        $this->api->resource(
            new MockResource(
                'customers',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('featureToggles')
                        ->type(Arr::make()->maxItems(1))
                        ->writable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/customers')->withParsedBody([
                'data' => ['type' => 'customers', 'attributes' => ['featureToggles' => [1, 2]]],
            ]),
        );
    }

    public function test_invalid_uniqueness()
    {
        $this->api->resource(
            new MockResource(
                'customers',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('featureToggles')
                        ->type(Arr::make()->uniqueItems())
                        ->writable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/customers')->withParsedBody([
                'data' => ['type' => 'customers', 'attributes' => ['featureToggles' => [1, 1]]],
            ]),
        );
    }

    public function test_valid_items_constraints()
    {
        $this->api->resource(
            new MockResource(
                'customers',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('featureToggles')
                        ->type(
                            Arr::make()
                                ->minItems(2)
                                ->maxItems(4)
                                ->uniqueItems(),
                        )
                        ->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/customers')->withParsedBody([
                'data' => ['type' => 'customers', 'attributes' => ['featureToggles' => [1, 2, 3]]],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['featureToggles' => [1, 2, 3]]]],
            $response->getBody(),
            true,
        );
    }

    public function test_invalid_items()
    {
        $this->api->resource(
            new MockResource(
                'customers',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('featureToggles')
                        ->type(Arr::make()->items(Str::make()->enum(['valid'])))
                        ->writable(),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/customers')->withParsedBody([
                'data' => [
                    'type' => 'customers',
                    'attributes' => ['featureToggles' => ['valid', 'invalid']],
                ],
            ]),
        );
    }

    public function test_valid_items()
    {
        $this->api->resource(
            new MockResource(
                'customers',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('featureToggles')
                        ->type(Arr::make()->items(Str::make()->enum(['valid1', 'valid2'])))
                        ->writable(),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/customers')->withParsedBody([
                'data' => [
                    'type' => 'customers',
                    'attributes' => ['featureToggles' => ['valid1', 'valid2']],
                ],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['featureToggles' => ['valid1', 'valid2']]]],
            $response->getBody(),
            true,
        );
    }

    public function test_schema()
    {
        $this->assertEquals(
            [
                'type' => 'array',
                'minItems' => 0,
                'maxItems' => null,
                'uniqueItems' => false,
                'items' => null,
            ],
            Arr::make()->schema(),
        );

        $this->assertEquals(
            [
                'type' => 'array',
                'minItems' => 1,
                'maxItems' => 10,
                'uniqueItems' => true,
                'items' => [
                    'type' => 'string',
                    'enum' => ['valid1', 'valid2'],
                    'x-enum-varnames' => ['valid1', 'valid2'],
                ],
            ],
            Arr::make()
                ->minItems(1)
                ->maxItems(10)
                ->uniqueItems()
                ->items(Str::make()->enum(['valid1', 'valid2']))
                ->schema(),
        );
    }
}
