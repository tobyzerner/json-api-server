<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FieldGetTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_use_getter_if_provided()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'raw' => 'raw', 'getter' => 'raw']],
                endpoints: [Show::make()],
                fields: [Attribute::make('raw'), Attribute::make('getter')->get(fn() => 'getter')],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['raw' => 'raw', 'getter' => 'getter']]],
            $response->getBody(),
        );
    }

    public function test_use_serializer_if_provided()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [
                    (object) [
                        'id' => '1',
                        'raw' => 'raw',
                        'serialized' => 'raw',
                        'getter' => 'raw',
                    ],
                ],
                endpoints: [Show::make()],
                fields: [
                    Attribute::make('raw'),
                    Attribute::make('serialized')->serialize(fn($value) => strtoupper($value)),
                    Attribute::make('getter')
                        ->get(fn() => 'getter')
                        ->serialize(fn($value) => strtoupper($value)),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            [
                'data' => [
                    'attributes' => ['raw' => 'raw', 'serialized' => 'RAW', 'getter' => 'GETTER'],
                ],
            ],
            $response->getBody(),
        );
    }

    public function test_use_serializer_before_type()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1', 'value' => 1]],
                endpoints: [Show::make()],
                fields: [
                    Attribute::make('value')
                        ->type(Str::make())
                        ->serialize(function ($value) {
                            if (is_string($value)) {
                                $this->fail('value should not yet be a string');
                            }
                            return $value + 1;
                        }),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['value' => '2']]],
            $response->getBody(),
        );
    }
}
