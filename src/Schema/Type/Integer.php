<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;

class Integer extends Number
{
    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): mixed
    {
        return (int) $value;
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $fail(new TypeMismatchException('integer', gettype($value)));
            return;
        }

        parent::validateValue($value, $fail);
    }

    protected function getSchema(): array
    {
        return ['type' => 'integer'] + parent::getSchema();
    }
}
