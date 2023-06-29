<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Str;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class StrTest extends AbstractTestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = new Context(new JsonApi(), new ServerRequest('GET', '/'));
    }

    public static function serializationProvider(): array
    {
        return [
            [Str::make('foo'), 'string', 'string'],
            [Str::make('foo'), 1, '1'],
            [Str::make('foo'), null, ''],
            [Str::make('foo')->nullable(), null, null],
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
            [Str::make('foo'), 'string', true],
            [Str::make('foo'), 1, false],
            [Str::make('foo'), null, false],
            [Str::make('foo')->nullable(), null, true],
            [Str::make('foo')->minLength(2), 'a', false],
            [Str::make('foo')->minLength(2), 'aa', true],
            [Str::make('foo')->maxLength(1), 'a', true],
            [Str::make('foo')->maxLength(1), 'aa', false],
            [Str::make('foo')->pattern('\d+'), '1', true],
            [Str::make('foo')->pattern('\d+'), 'a', false],
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
