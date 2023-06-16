<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Boolean;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class BooleanTest extends AbstractTestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new JsonApi(), new ServerRequest('GET', '/'));
    }

    public static function serializationProvider(): array
    {
        return [
            [Boolean::make('foo'), true, true],
            [Boolean::make('foo'), 'a', true],
            [Boolean::make('foo'), 1, true],
            [Boolean::make('foo'), false, false],
            [Boolean::make('foo'), 0, false],
            [Boolean::make('foo'), null, false],
            [Boolean::make('foo')->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Field $field, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $field->serializeValue($value, $this->context));
    }

    #[DataProvider('serializationProvider')]
    public function test_deserialization(Field $field, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $field->deserializeValue($value, $this->context));
    }

    public static function validationProvider(): array
    {
        return [
            [Boolean::make('foo'), true, true],
            [Boolean::make('foo'), false, true],
            [Boolean::make('foo'), 1, false],
            [Boolean::make('foo'), 0, false],
            [Boolean::make('foo'), '', false],
            [Boolean::make('foo'), null, false],
            [Boolean::make('foo')->nullable(), null, true],
        ];
    }

    #[DataProvider('validationProvider')]
    public function test_validation(Field $field, mixed $value, bool $valid)
    {
        $fail = $this->createMock(MockedCaller::class);

        if ($valid) {
            $fail->expects($this->never())->method('__invoke');
        } else {
            $fail->expects($this->once())->method('__invoke');
        }

        $field->validateValue($value, $fail, $this->context);
    }
}
