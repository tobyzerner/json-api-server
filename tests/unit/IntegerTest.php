<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Integer;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class IntegerTest extends AbstractTestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new JsonApi(), new ServerRequest('GET', '/'));
    }

    public static function serializationProvider(): array
    {
        return [
            [Integer::make('foo'), 1, 1],
            [Integer::make('foo'), '1', 1],
            [Integer::make('foo'), null, 0],
            [Integer::make('foo')->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Field $field, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $field->serializeValue($value, $this->context));
    }

    public static function validationProvider(): array
    {
        return [
            [Integer::make('foo'), 1, true],
            [Integer::make('foo'), 0, true],
            [Integer::make('foo'), 0.5, false],
            [Integer::make('foo'), true, false],
            [Integer::make('foo'), false, false],
            [Integer::make('foo'), '', false],
            [Integer::make('foo'), null, false],
            [Integer::make('foo')->nullable(), null, true],
            [Integer::make('foo')->minimum(10), 10, true],
            [Integer::make('foo')->minimum(10), 9, false],
            [Integer::make('foo')->minimum(10, exclusive: true), 11, true],
            [Integer::make('foo')->minimum(10, exclusive: true), 10, false],
            [Integer::make('foo')->maximum(10), 10, true],
            [Integer::make('foo')->maximum(10), 11, false],
            [Integer::make('foo')->maximum(10, exclusive: true), 9, true],
            [Integer::make('foo')->maximum(10, exclusive: true), 10, false],
            [Integer::make('foo')->multipleOf(2), 1, false],
            [Integer::make('foo')->multipleOf(2), 2, true],
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
