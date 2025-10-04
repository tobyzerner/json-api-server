<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FieldValidationTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public function test_validate_on_create()
    {
        $this->api->resource(
            new MockResource(
                'users',
                endpoints: [Create::make()],
                fields: [
                    Attribute::make('name')
                        ->writable()
                        ->validate(fn($value, $fail) => $fail()),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => ['type' => 'users', 'attributes' => ['name' => 'Toby']],
            ]),
        );
    }

    public function test_validate_on_update()
    {
        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Update::make()],
                fields: [
                    Attribute::make('name')
                        ->writable()
                        ->validate(fn($value, $fail) => $fail()),
                ],
            ),
        );

        $this->expectException(JsonApiErrorsException::class);

        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')->withParsedBody([
                'data' => ['type' => 'users', 'id' => '1', 'attributes' => ['name' => 'Toby']],
            ]),
        );
    }
}
