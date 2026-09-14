<?php

namespace Tobyz\Tests\JsonApiServer\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tobyz\JsonApiServer\Exception\Type\RangeViolationException;
use Tobyz\JsonApiServer\Schema\Type\Str;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\Tests\JsonApiServer\AbstractTestCase;
use Tobyz\Tests\JsonApiServer\MockedCaller;

class StrTest extends AbstractTestCase
{
    public static function serializationProvider(): array
    {
        return [
            [Str::make(), 'string', 'string'],
            [Str::make(), 1, '1'],
            [Str::make(), null, ''],
            [Str::make()->nullable(), null, null],
            [Str::make(), StrTestEnum::A, 'a'],
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
            [Str::make(), 'string', 'string'],
            [Str::make()->enum(['a', 'b']), 'a', 'a'],
            [Str::make()->enum(StrTestEnum::cases()), 'a', StrTestEnum::A],
            [Str::make()->enum(StrTestPureEnum::cases()), 'A', StrTestPureEnum::A],
            [Str::make()->enum(StrTestEnum::cases()), 'c', 'c'],
        ];
    }

    #[DataProvider('deserializationProvider')]
    public function test_deserialization(Type $type, mixed $value, mixed $expected)
    {
        $this->assertSame($expected, $type->deserialize($value));
    }

    public static function validationProvider(): array
    {
        return [
            [Str::make(), 'string', true],
            [Str::make(), 1, false],
            [Str::make(), null, false],
            [Str::make()->nullable(), null, true],
            [Str::make()->pattern('\d+'), '1', true],
            [Str::make()->pattern('\d+'), 'a', false],
            [Str::make()->enum(['a', 'b']), 'a', true],
            [Str::make()->enum(['a', 'b']), 'c', false],
            [Str::make()->enum(StrTestEnum::cases()), 'a', true],
            [Str::make()->enum(StrTestEnum::cases()), StrTestEnum::A, true],
            [Str::make()->enum(StrTestPureEnum::cases()), StrTestPureEnum::A, true],
            [Str::make()->enum(StrTestEnum::cases()), 'c', false],
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

    public static function lengthValidationProvider(): array
    {
        return [
            'ASCII at minimum' => ['minLength', 2, 'ab', null],
            'ASCII below minimum' => ['minLength', 2, 'a', 1],
            'ASCII at maximum' => ['maxLength', 1, 'a', null],
            'ASCII above maximum' => ['maxLength', 1, 'ab', 2],
            'accented at minimum' => ['minLength', 2, 'éé', null],
            'accented below minimum' => ['minLength', 2, 'é', 1],
            'accented at maximum' => ['maxLength', 1, 'é', null],
            'accented above maximum' => ['maxLength', 1, 'éé', 2],
            'emoji at minimum' => ['minLength', 2, '😀😀', null],
            'emoji below minimum' => ['minLength', 2, '😀', 1],
            'emoji at maximum' => ['maxLength', 1, '😀', null],
            'emoji above maximum' => ['maxLength', 1, '😀😀', 2],
            'combining accent at minimum' => ['minLength', 2, "e\u{0301}", null],
            'combining accent below minimum' => ['minLength', 3, "e\u{0301}", 2],
            'combining accent at maximum' => ['maxLength', 2, "e\u{0301}", null],
            'combining accent above maximum' => ['maxLength', 1, "e\u{0301}", 2],
        ];
    }

    #[DataProvider('lengthValidationProvider')]
    public function test_length_validation_counts_unicode_code_points(
        string $constraint,
        int $limit,
        string $value,
        ?int $actual,
    ) {
        $fail = $this->createMock(MockedCaller::class);

        if ($actual === null) {
            $fail->expects($this->never())->method('__invoke');
        } else {
            $fail
                ->expects($this->once())
                ->method('__invoke')
                ->with($this->equalTo(new RangeViolationException($constraint, $limit, $actual)));
        }

        Str::make()->$constraint($limit)->validate($value, $fail);
    }

    public static function schemaProvider(): array
    {
        return [
            [Str::make(), ['type' => 'string']],
            [Str::make()->nullable(), ['type' => 'string', 'nullable' => true]],
            [
                Str::make()->minLength(2)->maxLength(10),
                ['type' => 'string', 'minLength' => 2, 'maxLength' => 10],
            ],
        ];
    }

    #[DataProvider('schemaProvider')]
    public function test_schema(Type $type, array $expected)
    {
        $this->assertEquals($expected, $type->schema());
    }
}

enum StrTestEnum: string
{
    case A = 'a';
    case B = 'b';
}

enum StrTestPureEnum
{
    case A;
    case B;
}
