<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FieldSetTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_use_deserializer_if_provided()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('raw')->writable(),
                    Attribute::make('deserialized')
                        ->writable()
                        ->deserialize(fn($value) => strtoupper($value)),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'attributes' => ['raw' => 'raw', 'deserialized' => 'raw'],
                ],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['raw' => 'raw', 'deserialized' => 'RAW']]],
            $response->getBody(),
        );
    }

    public function test_use_setter_if_provided()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('raw')->writable(),
                    Attribute::make('setter')
                        ->writable()
                        ->set(fn($model, $value) => ($model->setter = strtoupper($value))),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'attributes' => ['raw' => 'raw', 'setter' => 'raw'],
                ],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['raw' => 'raw', 'setter' => 'RAW']]],
            $response->getBody(),
        );
    }

    public function test_use_saver_if_provided()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('raw')->writable(),
                    Attribute::make('saver')
                        ->writable()
                        ->save(fn($model, $value) => ($model->saver = strtoupper($value))),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'attributes' => ['raw' => 'raw', 'saver' => 'raw'],
                ],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['raw' => 'raw', 'saver' => 'RAW']]],
            $response->getBody(),
        );
    }
}
