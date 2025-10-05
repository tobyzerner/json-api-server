<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Number;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class NumberTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [Number::make(), 1, 1.0],
            [Number::make(), '1', 1.0],
            [Number::make(), null, 0.0],
            [Number::make()->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->serialize($value));
    }

    public static function validationProvider(): array
    {
        return [
            [Number::make(), 1, true],
            [Number::make(), 0, true],
            [Number::make(), true, false],
            [Number::make(), false, false],
            [Number::make(), '', false],
            [Number::make(), null, false],
            [Number::make()->nullable(), null, true],
            [Number::make()->minimum(10), 10, true],
            [Number::make()->minimum(10), 9, false],
            [Number::make()->minimum(10, exclusive: true), 11, true],
            [Number::make()->minimum(10, exclusive: true), 10, false],
            [Number::make()->maximum(10), 10, true],
            [Number::make()->maximum(10), 11, false],
            [Number::make()->maximum(10, exclusive: true), 9, true],
            [Number::make()->maximum(10, exclusive: true), 10, false],
            [Number::make()->multipleOf(2), 1, false],
            [Number::make()->multipleOf(2), 2, true],
            [Number::make()->multipleOf(0.01), 100, true],
            [Number::make()->multipleOf(0.01), 100.5, true],
            [Number::make()->multipleOf(0.01), 100.56, true],
            [Number::make()->multipleOf(0.01), 100.567, false],
        ];
    }

    #[DataProvider('validationProvider')]
    public function test_validation(Type $type, mixed $value, bool $valid)
    {
        $fail = $this->createMock(MockedCaller::class);

        if ($valid) {
            $fail->expects($this->never())->method('__invoke');
        } else {
            $fail->expects($this->once())->method('__invoke');
        }

        $type->validate($value, $fail);
    }

    public function test_multipleOf_reset(): void
    {
        $number = Number::make()
            ->multipleOf(2)
            ->multipleOf(null);

        $fail = $this->createMock(MockedCaller::class);
        $fail->expects($this->never())->method('__invoke');

        $number->validate(5, $fail);
    }

    public static function schemaProvider(): array
    {
        return [
            [Number::make(), ['type' => 'number']],
            [Number::make()->nullable(), ['type' => 'number', 'nullable' => true]],
            [
                Number::make()
                    ->minimum(10)
                    ->maximum(100),
                ['type' => 'number', 'minimum' => 10.0, 'maximum' => 100.0],
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}
