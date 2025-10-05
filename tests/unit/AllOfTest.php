<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\AllOf;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class AllOfTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [AllOf::make([Str::make(), Str::make()->minLength(2)]), 'hello', 'hello'],
            [AllOf::make([Str::make()])->nullable(), null, null],
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
            // Must pass all validators
            [AllOf::make([Str::make(), Str::make()->minLength(2)]), 'hello', true],
            [AllOf::make([Str::make(), Str::make()->minLength(2)]), 'hi', true],
            [AllOf::make([Str::make(), Str::make()->minLength(2)]), 'a', false], // fails minLength
            [AllOf::make([Str::make(), Str::make()->minLength(2)]), 123, false], // fails Str
            [AllOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), '12345', true],
            [AllOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), '123', false], // fails minLength
            [AllOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), 'abcde', false], // fails pattern
            [AllOf::make([Str::make()])->nullable(), null, true],
        ];
    }

    #[DataProvider('validationProvider')]
    public function test_validation(Type $type, mixed $value, bool $valid)
    {
        $fail = $this->createMock(MockedCaller::class);

        if ($valid) {
            $fail->expects($this->never())->method('__invoke');
        } else {
            $fail->expects($this->atLeastOnce())->method('__invoke');
        }

        $type->validate($value, $fail);
    }

    public static function schemaProvider(): array
    {
        return [
            [
                AllOf::make([Str::make(), Str::make()->minLength(5)]),
                [
                    'allOf' => [['type' => 'string'], ['type' => 'string', 'minLength' => 5]],
                ],
            ],
            [
                AllOf::make([Str::make()])->nullable(),
                [
                    'allOf' => [['type' => 'string']],
                    'nullable' => true,
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
