<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class StrTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [[Str::make(), 'string', 'string'], [Str::make(), 1, '1'], [Str::make(), null, '']];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->serialize($value));
    }

    public static function validationProvider(): array
    {
        return [
            [Str::make(), 'string', true],
            [Str::make(), 1, false],
            [Str::make(), null, false],
            [Str::make()->minLength(2), 'a', false],
            [Str::make()->minLength(2), 'aa', true],
            [Str::make()->maxLength(1), 'a', true],
            [Str::make()->maxLength(1), 'aa', false],
            [Str::make()->pattern('\d+'), '1', true],
            [Str::make()->pattern('\d+'), 'a', false],
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
