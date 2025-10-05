<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Not;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class NotTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [Not::make(Str::make()), 123, 123],
            [Not::make(Str::make()), true, true],
            [Not::make(Str::make())->nullable(), null, null],
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
            // Must NOT pass the inner validator
            [Not::make(Str::make()), 123, true], // not a string - valid
            [Not::make(Str::make()), true, true], // not a string - valid
            [Not::make(Str::make()), 'hello', false], // is a string - invalid
            [Not::make(Str::make()->minLength(5)), 'ab', true], // fails minLength - valid for Not
            [Not::make(Str::make()->minLength(5)), 'hello', false], // passes minLength - invalid for Not
            [Not::make(Str::make())->nullable(), null, true],
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
                Not::make(Str::make()),
                [
                    'not' => ['type' => 'string'],
                ],
            ],
            [
                Not::make(Str::make())->nullable(),
                [
                    'not' => ['type' => 'string'],
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
