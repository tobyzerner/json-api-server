<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Date;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class DateTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [Date::make(), new \DateTime('1993-04-04'), '1993-04-04'],
            [Date::make(), '1993-04-04', '1993-04-04'],
            [Date::make(), null, null],
            [Date::make()->nullable(), null, null],
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
            [Date::make(), '1993-04-04', new \DateTime('1993-04-04')],
            [Date::make(), null, null],
            [Date::make()->nullable(), null, null],
        ];
    }

    #[DataProvider('deserializationProvider')]
    public function test_deserialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertEquals($expected, $type->deserialize($value));
    }

    public static function validationProvider(): array
    {
        return [
            [Date::make(), new \DateTime(), true],
            [Date::make(), 'string', false],
            [Date::make(), null, false],
            [Date::make()->nullable(), null, true],
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
            [Date::make(), ['type' => 'string', 'format' => 'date']],
            [
                Date::make()->nullable(),
                ['type' => 'string', 'format' => 'date', 'nullable' => true],
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}
