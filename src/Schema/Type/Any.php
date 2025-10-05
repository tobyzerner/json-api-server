<?php

namespace Tobyz\JsonApiServer\Schema\Type;

class Any extends AbstractType
{
    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value;
    }

    protected function deserializeValue(mixed $value): mixed
    {
        return $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        // No validation - accepts any value including null
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        // No validation - accepts any value
    }

    protected function getSchema(): array
    {
        return [];
    }
}
