<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FieldDefaultTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_default_closure_value_used_if_field_not_present()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('name')
                        ->writable()
                        ->default(fn() => 'default'),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users'],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['name' => 'default']]],
            $response->getBody(),
        );
    }

    public function test_default_literal_value_used_if_field_not_present()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('name')
                        ->writable()
                        ->default('default'),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users'],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['name' => 'default']]],
            $response->getBody(),
        );
    }

    public function test_default_value_not_used_if_field_present()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('name')
                        ->writable()
                        ->default(fn() => 'default'),
                ],
            ),
        );

        $response = $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['name' => 'Toby']],
            ]),
        );

        $this->assertJsonApiDocumentSubset(
            ['data' => ['attributes' => ['name' => 'Toby']]],
            $response->getBody(),
        );
    }
}
