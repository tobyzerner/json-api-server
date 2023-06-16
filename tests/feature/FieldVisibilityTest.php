<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Show;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FieldVisibilityTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_field_visibility()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Show::make()],
                fields: [
                    Attribute::make('visible'),
                    Attribute::make('visible2')->visible(),
                    Attribute::make('visible3')->visible(fn() => true),
                    Attribute::make('visible4')->hidden(fn() => false),
                    Attribute::make('hidden')->hidden(),
                    Attribute::make('hidden3')->hidden(fn() => true),
                    Attribute::make('hidden2')->visible(fn() => false),
                ],
            ),
        );

        $response = $this->api->handle($this->buildRequest('GET', '/users/1'));
        $document = json_decode($response->getBody(), true);

        $this->assertArrayHasKey('visible', $document['data']['attributes']);
        $this->assertArrayHasKey('visible2', $document['data']['attributes']);
        $this->assertArrayHasKey('visible3', $document['data']['attributes']);
        $this->assertArrayHasKey('visible4', $document['data']['attributes']);

        $this->assertArrayNotHasKey('hidden', $document['data']['attributes']);
        $this->assertArrayNotHasKey('hidden2', $document['data']['attributes']);
        $this->assertArrayNotHasKey('hidden3', $document['data']['attributes']);
        $this->assertArrayNotHasKey('hidden4', $document['data']['attributes']);
    }
}
