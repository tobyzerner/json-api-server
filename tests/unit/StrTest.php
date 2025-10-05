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
        return [
            [Str::make(), 'string', 'string'],
            [Str::make(), 1, '1'],
            [Str::make(), null, ''],
            [Str::make()->nullable(), null, null],
            [Str::make(), StrTestEnum::A, 'a'],
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
            [Str::make(), 'string', true],
            [Str::make(), 1, false],
            [Str::make(), null, false],
            [Str::make()->nullable(), null, true],
            [Str::make()->minLength(2), 'a', false],
            [Str::make()->minLength(2), 'aa', true],
            [Str::make()->maxLength(1), 'a', true],
            [Str::make()->maxLength(1), 'aa', false],
            [Str::make()->pattern('\d+'), '1', true],
            [Str::make()->pattern('\d+'), 'a', false],
            [Str::make()->enum(['a', 'b']), 'a', true],
            [Str::make()->enum(['a', 'b']), 'c', false],
            [Str::make()->enum(StrTestEnum::cases()), 'a', true],
            [Str::make()->enum(StrTestEnum::cases()), 'c', false],
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

    public static function schemaProvider(): array
    {
        return [
            [Str::make(), ['type' => 'string']],
            [Str::make()->nullable(), ['type' => 'string', 'nullable' => true]],
            [
                Str::make()->minLength(2)->maxLength(10),
                ['type' => 'string', 'minLength' => 2, 'maxLength' => 10],
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}

enum StrTestEnum: string
{
    case A = 'a';
    case B = 'b';
}
