<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;

class Boolean extends AbstractType
{
    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): bool
    {
        return (bool) $value;
    }

    protected function deserializeValue(mixed $value): mixed
    {
        return $value;
    }

    public function deserializeQueryValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized === null ? $value : $normalized;
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (!is_bool($value)) {
            $fail(new TypeMismatchException('boolean', gettype($value)));
        }
    }

    protected function getSchema(): array
    {
        return ['type' => 'boolean'];
    }
}
