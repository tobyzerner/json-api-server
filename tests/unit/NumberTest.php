<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Number;
use Tobyz\JsonApiServer\Schema\Type\TypeInterface;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class NumberTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [[Number::make(), 1, 1.0], [Number::make(), '1', 1.0], [Number::make(), null, 0.0]];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(TypeInterface $type, mixed $value, mixed $expected)
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
        ];
    }

    #[DataProvider('validationProvider')]
    public function test_validation(TypeInterface $type, mixed $value, bool $valid)
    {
        $fail = $this->createMock(MockedCaller::class);

        if ($valid) {
            $fail->expects($this->never())->method('__invoke');
        } else {
            $fail->expects($this->once())->method('__invoke');
        }

        $type->validate($value, $fail);
    }
}
