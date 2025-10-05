<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\AnyOf;
use Tobyz\JsonApiServer\Schema\Type\Integer;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class AnyOfTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [AnyOf::make([Integer::make(), Str::make()]), 123, 123],
            [AnyOf::make([Integer::make(), Str::make()]), 'hello', 'hello'],
            [AnyOf::make([Integer::make(), Str::make()])->nullable(), null, null],
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
            // Must pass at least one validator
            [AnyOf::make([Integer::make(), Str::make()]), 123, true],
            [AnyOf::make([Integer::make(), Str::make()]), 'hello', true],
            [AnyOf::make([Integer::make(), Str::make()]), true, false], // fails both
            [AnyOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), 'hello', true], // passes first
            [AnyOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), '123', true], // passes second
            [
                AnyOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]),
                '12345',
                true,
            ], // passes both (ok for anyOf)
            [AnyOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), 'ab', false], // fails both
            [AnyOf::make([Integer::make()])->nullable(), null, true],
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
                AnyOf::make([Integer::make(), Str::make()]),
                [
                    'anyOf' => [
                        ['type' => 'integer'],
                        ['type' => 'string'],
                    ],
                ],
            ],
            [
                AnyOf::make([Integer::make()])->nullable(),
                [
                    'anyOf' => [
                        ['type' => 'integer'],
                        ['type' => 'null'],
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
