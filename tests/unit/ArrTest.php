<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Arr;
use Tobyz\JsonApiServer\Schema\Type\Date;
use Tobyz\JsonApiServer\Schema\Type\Integer;
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
            [
                Arr::make()->items(Date::make()),
                [new \DateTime('1993-04-04')],
                ['1993-04-04'],
            ],
            [Arr::make(), [], []],
            [Arr::make()->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->serialize($value));
    }

    public static function deserializationProvider(): array
    {
        return [
            [Arr::make(), [1, 2, 3], [1, 2, 3]],
            [Arr::make()->items(Date::make()), ['1993-04-04'], [new \DateTime('1993-04-04')]],
            [Arr::make(), [], []],
            [Arr::make()->nullable(), null, null],
        ];
    }

    #[DataProvider('deserializationProvider')]
    public function test_deserialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertEquals($expected, $type->deserialize($value));
    }

    public static function queryDeserializationProvider(): array
    {
        return [
            [Arr::make(), '1', ['1']],
            [Arr::make(), ['1', '2'], ['1', '2']],
            [Arr::make()->items(Integer::make()), '1', [1]],
            [Arr::make()->items(Integer::make()), ['1', '2'], [1, 2]],
            [Arr::make()->commaSeparated(), '1,2', ['1', '2']],
            [Arr::make()->commaSeparated(), ['1,2', '3'], ['1', '2', '3']],
            [Arr::make()->commaSeparated(), ['1,2', 3], ['1', '2', 3]],
            [Arr::make(), null, null],
            [Arr::make()->nullable(), null, null],
            [Arr::make()->items(Integer::make()), null, null],
            [Arr::make()->items(Integer::make())->commaSeparated(), null, null],
        ];
    }

    #[DataProvider('queryDeserializationProvider')]
    public function test_query_deserialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->deserializeQueryValue($value));
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
            [
                Arr::make()->items(Date::make())->uniqueItems(),
                [new \DateTime('1993-04-04'), new \DateTime('1993-04-05')],
                true,
            ],
            [
                Arr::make()->items(Date::make())->uniqueItems(),
                [new \DateTime('1993-04-04'), new \DateTime('1993-04-04')],
                false,
            ],
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

    public function test_nested_validation_passes_through_plain_errors(): void
    {
        $type = Arr::make()->items(
            new class implements Type {
                public function serialize(mixed $value): mixed
                {
                    return $value;
                }

                public function deserialize(mixed $value): mixed
                {
                    return $value;
                }

                public function deserializeQueryValue(mixed $value): mixed
                {
                    return $this->deserialize($value);
                }

                public function validate(mixed $value, callable $fail): void
                {
                    $fail('invalid');
                }

                public function schema(): array
                {
                    return [];
                }
            },
        );

        $type->validate(['Toby'], function ($error) {
            $this->assertSame('invalid', $error);
        });
    }

    public static function schemaProvider(): array
    {
        return [
            [Arr::make(), ['type' => 'array']],
            [Arr::make()->nullable(), ['type' => 'array', 'nullable' => true]],
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
                ],
            ],
            [
                Arr::make()->items(Str::make()->nullable()),
                [
                    'type' => 'array',
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
