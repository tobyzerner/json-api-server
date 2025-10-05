<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\DateTime;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class DateTimeTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [DateTime::make(), new \DateTime('1993-04-04T12:34:56Z'), '1993-04-04T12:34:56+00:00'],
            [DateTime::make(), '1993-04-04T12:34:56Z', '1993-04-04T12:34:56Z'],
            [DateTime::make(), null, null],
            [DateTime::make()->nullable(), null, null],
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
            [DateTime::make(), '1993-04-04T12:34:56Z', new \DateTime('1993-04-04T12:34:56Z')],
            [DateTime::make(), null, null],
            [DateTime::make()->nullable(), null, null],
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
            [DateTime::make(), new \DateTime(), true],
            [DateTime::make(), '1993-04-04', false],
            [DateTime::make(), 'string', false],
            [DateTime::make(), null, false],
            [DateTime::make()->nullable(), null, true],
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
            [DateTime::make(), ['type' => 'string', 'format' => 'date-time']],
            [
                DateTime::make()->nullable(),
                ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}
