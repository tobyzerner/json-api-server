<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Integer;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class IntegerTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [[Integer::make(), 1, 1], [Integer::make(), '1', 1], [Integer::make(), null, 0]];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->serialize($value));
    }

    public static function validationProvider(): array
    {
        return [
            [Integer::make(), 1, true],
            [Integer::make(), 0, true],
            [Integer::make(), 0.5, false],
            [Integer::make(), true, false],
            [Integer::make(), false, false],
            [Integer::make(), '', false],
            [Integer::make(), null, false],
            [Integer::make()->minimum(10), 10, true],
            [Integer::make()->minimum(10), 9, false],
            [Integer::make()->minimum(10, exclusive: true), 11, true],
            [Integer::make()->minimum(10, exclusive: true), 10, false],
            [Integer::make()->maximum(10), 10, true],
            [Integer::make()->maximum(10), 11, false],
            [Integer::make()->maximum(10, exclusive: true), 9, true],
            [Integer::make()->maximum(10, exclusive: true), 10, false],
            [Integer::make()->multipleOf(2), 1, false],
            [Integer::make()->multipleOf(2), 2, true],
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
}
