<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Boolean;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class BooleanTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [Boolean::make(), true, true],
            [Boolean::make(), 'a', true],
            [Boolean::make(), 1, true],
            [Boolean::make(), false, false],
            [Boolean::make(), 0, false],
            [Boolean::make(), null, false],
            [Boolean::make()->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->serialize($value));
    }

    public static function queryDeserializationProvider(): array
    {
        return [
            ['true', true],
            ['false', false],
            ['1', true],
            ['0', false],
            ['', false],
            ['sometimes', 'sometimes'],
            [null, null],
        ];
    }

    #[DataProvider('queryDeserializationProvider')]
    public function test_query_deserialization(mixed $value, mixed $expected)
    {
        $this->assertSame($expected, Boolean::make()->deserializeQueryValue($value));
    }

    public static function validationProvider(): array
    {
        return [
            [Boolean::make(), true, true],
            [Boolean::make(), false, true],
            [Boolean::make(), 1, false],
            [Boolean::make(), 0, false],
            [Boolean::make(), '', false],
            [Boolean::make(), null, false],
            [Boolean::make()->nullable(), null, true],
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
            [Boolean::make(), ['type' => 'boolean']],
            [Boolean::make()->nullable(), ['type' => 'boolean', 'nullable' => true]],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}
