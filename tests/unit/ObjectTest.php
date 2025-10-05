<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Schema\Type\Integer;
use Tobyz\JsonApiServer\Schema\Type\Obj;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class ObjectTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [
                Obj::make()
                    ->property('name', Str::make())
                    ->property('age', Integer::make()),
                ['name' => 'John', 'age' => 30],
                ['name' => 'John', 'age' => 30],
            ],
            [
                Obj::make()->property('name', Str::make()),
                ['name' => 'John', 'extra' => 'value'],
                ['name' => 'John', 'extra' => 'value'],
            ],
            [Obj::make()->property('age', Integer::make()), ['age' => '30'], ['age' => 30]],
            [Obj::make()->nullable(), null, null],
        ];
    }

    #[DataProvider('serializationProvider')]
    public function test_serialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertEquals($expected, $type->serialize($value));
    }

    public static function validationProvider(): array
    {
        return [
            [Obj::make(), [], true],
            [Obj::make(), ['key' => 'value'], true],
            [Obj::make(), 'not an object', false],
            [Obj::make(), 123, false],
            [Obj::make(), null, false],
            [Obj::make()->nullable(), null, true],
            [Obj::make()->property('name', Str::make()), ['name' => 'John'], true],
            [Obj::make()->property('name', Str::make()), ['name' => 123], false],
            [Obj::make()->property('name', Str::make(), required: true), [], false],
            [Obj::make()->property('name', Str::make(), required: true), ['name' => 'John'], true],
            [Obj::make()->additionalProperties(false), ['extra' => 'value'], false],
            [Obj::make()->additionalProperties(true), ['extra' => 'value'], true],
            [
                Obj::make()
                    ->property('name', Str::make())
                    ->additionalProperties(false),
                ['name' => 'John'],
                true,
            ],
            [
                Obj::make()
                    ->property('name', Str::make())
                    ->additionalProperties(false),
                ['name' => 'John', 'extra' => 'value'],
                false,
            ],
            [Obj::make()->additionalProperties(Str::make()), ['key' => 'value'], true],
            [Obj::make()->additionalProperties(Str::make()), ['key' => 123], false],
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
            [Obj::make(), ['type' => 'object']],
            [Obj::make()->nullable(), ['type' => 'object', 'nullable' => true]],
            [
                Obj::make()->property('name', Str::make()),
                [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
            [
                Obj::make()->property('name', Str::make(), required: true),
                [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                Obj::make()
                    ->property('name', Str::make(), required: true)
                    ->property('age', Integer::make()),
                [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'age' => ['type' => 'integer'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                Obj::make()->additionalProperties(false),
                ['type' => 'object', 'additionalProperties' => false],
            ],
            [
                Obj::make()->additionalProperties(true),
                ['type' => 'object', 'additionalProperties' => true],
            ],
            [
                Obj::make()->additionalProperties(Str::make()),
                ['type' => 'object', 'additionalProperties' => ['type' => 'string']],
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}
