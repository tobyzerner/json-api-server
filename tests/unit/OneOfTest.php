<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Integer;
use Tobyz\JsonApiServer\Schema\Type\OneOf;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class OneOfTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [OneOf::make([Integer::make(), Str::make()]), 123, 123],
            [OneOf::make([Integer::make(), Str::make()]), 'hello', 'hello'],
            [OneOf::make([Integer::make(), Str::make()])->nullable(), null, null],
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
            // Must pass exactly one validator
            [OneOf::make([Integer::make(), Str::make()]), 123, true], // passes Integer only
            [OneOf::make([Integer::make(), Str::make()]), 'hello', true], // passes Str only
            [OneOf::make([Integer::make(), Str::make()]), true, false], // passes neither
            [OneOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), 'hello', true], // passes first only
            [OneOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), '123', true], // passes second only
            [OneOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), '12345', false], // passes both - invalid for oneOf!
            [OneOf::make([Str::make()->minLength(5), Str::make()->pattern('\d+')]), 'ab', false], // passes neither
            [OneOf::make([Integer::make()])->nullable(), null, true],
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
                OneOf::make([Integer::make(), Str::make()]),
                [
                    'oneOf' => [['type' => 'integer'], ['type' => 'string']],
                ],
            ],
            [
                OneOf::make([Integer::make()])->nullable(),
                [
                    'oneOf' => [['type' => 'integer'], ['type' => 'null']],
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
