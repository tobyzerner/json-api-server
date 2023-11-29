<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\DateTime;
use Tobyz\JsonApiServer\Schema\Type\TypeInterface;
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
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(TypeInterface $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->serialize($value));
    }

    public static function deserializationProvider(): array
    {
        return [
            [DateTime::make(), '1993-04-04T12:34:56Z', new \DateTime('1993-04-04T12:34:56Z')],
            [DateTime::make(), null, null],
        ];
    }

    #[DataProvider('deserializationProvider')]
    public function test_deserialization(TypeInterface $type, mixed $value, mixed $expected)
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
        ];
    }

    #[DataProvider('validationProvider')]
    public function test_validation(TypeInterface $type, mixed $value, bool $valid)
    {
        $fail = $this->createMock(MockedCaller::class);

        if ($valid) {
            $fail->expects($this->never())->method('__invoke');
        } else {
            $fail->expects($this->once())->method('__invoke');
        }

        $type->validate($value, $fail);
    }
}
