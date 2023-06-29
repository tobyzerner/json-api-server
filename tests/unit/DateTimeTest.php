<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\DateTime;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class DateTimeTest extends AbstractTestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new JsonApi(), new ServerRequest('GET', '/'));
    }

    public static function serializationProvider(): array
    {
        return [
            [
                DateTime::make('foo'),
                new \DateTime('1993-04-04T12:34:56Z'),
                '1993-04-04T12:34:56+00:00',
            ],
            [DateTime::make('foo'), '1993-04-04T12:34:56Z', '1993-04-04T12:34:56Z'],
            [DateTime::make('foo'), null, null],
            [DateTime::make('foo')->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Field $field, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $field->serializeValue($value, $this->context));
    }

    public static function deserializationProvider(): array
    {
        return [
            [DateTime::make('foo'), '1993-04-04T12:34:56Z', new \DateTime('1993-04-04T12:34:56Z')],
            [DateTime::make('foo'), null, null],
            [DateTime::make('foo')->nullable(), null, null],
        ];
    }

    #[DataProvider('deserializationProvider')]
    public function test_deserialization(Field $field, mixed $value, mixed $expected)
    {
        $this->assertEquals($expected, $field->deserializeValue($value, $this->context));
    }

    public static function validationProvider(): array
    {
        return [
            [DateTime::make('foo'), new \DateTime(), true],
            [DateTime::make('foo'), '1993-04-04', false],
            [DateTime::make('foo'), 'string', false],
            [DateTime::make('foo'), null, false],
            [DateTime::make('foo')->nullable(), null, true],
        ];
    }

    #[DataProvider('validationProvider')]
    public function test_validation(Field $field, mixed $value, bool $valid)
    {
        $fail = $this->createMock(MockedCaller::class);

        if ($valid) {
            $fail->expects($this->never())->method('__invoke');
        } else {
            $fail->expects($this->once())->method('__invoke');
        }

        $field->validateValue($value, $fail, $this->context);
    }
}
