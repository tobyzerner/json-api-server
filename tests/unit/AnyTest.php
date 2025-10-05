<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Any;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class AnyTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [Any::make(), 'string', 'string'],
            [Any::make(), 123, 123],
            [Any::make(), true, true],
            [Any::make(), null, null],
            [Any::make(), ['a', 'b'], ['a', 'b']],
            [Any::make()->nullable(), null, null],
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
            [Any::make(), 'string', true],
            [Any::make(), 123, true],
            [Any::make(), true, true],
            [Any::make(), null, true],
            [Any::make(), ['a', 'b'], true],
            [Any::make()->nullable(), null, true],
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
            [Any::make(), []],
            [Any::make()->nullable(), ['nullable' => true]],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}
