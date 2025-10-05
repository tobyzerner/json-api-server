<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Arr;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class ArrTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [Arr::make(), [1, 2, 3], [1, 2, 3]],
            [Arr::make(), [], []],
            [Arr::make()->nullable(), null, null],
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
            [Arr::make(), [], true],
            [Arr::make(), [1, 2, 3], true],
            [Arr::make(), 'string', false],
            [Arr::make(), null, false],
            [Arr::make()->nullable(), null, true],
            [Arr::make()->minItems(1), [], false],
            [Arr::make()->minItems(1), [1], true],
            [Arr::make()->maxItems(2), [1, 2], true],
            [Arr::make()->maxItems(2), [1, 2, 3], false],
            [Arr::make()->uniqueItems(), [1, 2], true],
            [Arr::make()->uniqueItems(), [1, 1], false],
            [Arr::make()->items(Str::make()), ['a', 'b'], true],
            [Arr::make()->items(Str::make()), ['a', 1], false],
            [Arr::make()->items(Str::make()->nullable()), ['a', null, 'b'], true],
            [Arr::make()->items(Str::make()), ['a', null], false],
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
            [
                Arr::make(),
                [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => null,
                    'uniqueItems' => false,
                    'items' => null,
                ],
            ],
            [
                Arr::make()->nullable(),
                [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => null,
                    'uniqueItems' => false,
                    'items' => null,
                    'nullable' => true,
                ],
            ],
            [
                Arr::make()
                    ->minItems(1)
                    ->maxItems(10)
                    ->uniqueItems(),
                [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 10,
                    'uniqueItems' => true,
                    'items' => null,
                ],
            ],
            [
                Arr::make()->items(Str::make()->nullable()),
                [
                    'type' => 'array',
                    'minItems' => 0,
                    'maxItems' => null,
                    'uniqueItems' => false,
                    'items' => [
                        'type' => 'string',
                        'nullable' => true,
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}
