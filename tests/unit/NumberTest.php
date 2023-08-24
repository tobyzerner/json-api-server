<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Number;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class NumberTest extends AbstractTestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new JsonApi(), new ServerRequest('GET', '/'));
    }

    public static function serializationProvider(): array
    {
        return [
            [Number::make('foo'), 1, 1.0],
            [Number::make('foo'), '1', 1.0],
            [Number::make('foo'), null, 0.0],
            [Number::make('foo')->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Field $field, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $field->serializeValue($value, $this->context));
    }

    public static function validationProvider(): array
    {
        return [
            [Number::make('foo'), 1, true],
            [Number::make('foo'), 0, true],
            [Number::make('foo'), true, false],
            [Number::make('foo'), false, false],
            [Number::make('foo'), '', false],
            [Number::make('foo'), null, false],
            [Number::make('foo')->nullable(), null, true],
            [Number::make('foo')->minimum(10), 10, true],
            [Number::make('foo')->minimum(10), 9, false],
            [Number::make('foo')->minimum(10, exclusive: true), 11, true],
            [Number::make('foo')->minimum(10, exclusive: true), 10, false],
            [Number::make('foo')->maximum(10), 10, true],
            [Number::make('foo')->maximum(10), 11, false],
            [Number::make('foo')->maximum(10, exclusive: true), 9, true],
            [Number::make('foo')->maximum(10, exclusive: true), 10, false],
            [Number::make('foo')->multipleOf(2), 1, false],
            [Number::make('foo')->multipleOf(2), 2, true],
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
