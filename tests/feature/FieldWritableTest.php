<?php

namespace Tobyz\Tests\JsonApiServer\feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Endpoint\Create;
use Tobyz\JsonApiServer\Endpoint\Update;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockResource;

class FieldWritableTest extends AbstractTestCase
{
    private JsonApi $api;

    public function setUp(): void
    {
        $this->api = new JsonApi();
    }

    public static function writableProvider(): array
    {
        return [
            [Attribute::make('foo'), false],
            [Attribute::make('foo')->writable(fn() => false), false],
            [Attribute::make('foo')->writable(), true],
            [Attribute::make('foo')->writable(fn() => true), true],
        ];
    }

    #[DataProvider('writableProvider')]
    public function test_writable_update(Field $field, bool $shouldSucceed)
    {
        if ($shouldSucceed) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(ForbiddenException::class);
        }

        $this->api->resource(
            new MockResource(
                'users',
                models: [(object) ['id' => '1']],
                endpoints: [Update::make()],
                fields: [$field],
            ),
        );

        $this->api->handle(
            $this->buildRequest('PATCH', '/users/1')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'id' => '1',
                    'attributes' => ['foo' => 'bar'],
                ],
            ]),
        );
    }

    public static function writableCreateProvider(): array
    {
        return [[Attribute::make('foo')->writableOnCreate(), true]];
    }

    #[DataProvider('writableProvider')]
    #[DataProvider('writableCreateProvider')]
    public function test_writable_create(Field $field, bool $shouldSucceed)
    {
        if ($shouldSucceed) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(ForbiddenException::class);
        }

        $this->api->resource(
            new MockResource('users', endpoints: [Create::make()], fields: [$field]),
        );

        $this->api->handle(
            $this->buildRequest('POST', '/users')->withParsedBody([
                'data' => [
                    'type' => 'users',
                    'attributes' => ['foo' => 'bar'],
                ],
            ]),
        );
    }
}
