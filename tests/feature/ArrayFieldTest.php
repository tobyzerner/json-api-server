<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\ArrayField;
use Tobyz\JsonApiServer\Schema\Field\Str;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class ArrayFieldTest extends AbstractTestCase
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
                fields: [ArrayField::make('featureToggles')->writable()],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

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
                    ArrayField::make('featureToggles')
                        ->minItems(1)
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

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
                    ArrayField::make('featureToggles')
                        ->maxItems(1)
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

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
                    ArrayField::make('featureToggles')
                        ->uniqueItems()
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

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
                    ArrayField::make('featureToggles')
                        ->minItems(2)
                        ->maxItems(4)
                        ->uniqueItems()
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
                    ArrayField::make('featureToggles')
                        ->items(Str::make('')->enum(['valid']))
                        ->writable(),
                ],
            ),
        );

        $this->expectException(UnprocessableEntityException::class);

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
                    ArrayField::make('featureToggles')
                        ->items(Str::make('')->enum(['valid1', 'valid2']))
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
                'description' => null,
                'nullable' => false,
            ],
            ArrayField::make('featureToggles')->getSchema(),
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
                ],
                'description' => null,
                'nullable' => false,
            ],
            ArrayField::make('featureToggles')
                ->minItems(1)
                ->maxItems(10)
                ->uniqueItems()
                ->items(Str::make('')->enum(['valid1', 'valid2']))
                ->getSchema(),
        );
    }
}
