<?php

namespace Tobyz\JsonApiServer\Schema\Type;

class Boolean implements Type
{
    public static function make(): static
    {
        return new static();
    }

    public function serialize(mixed $value): bool
    {
        return (bool) $value;
    }

    public function deserialize(mixed $value): mixed
    {
        return $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        if (!is_bool($value)) {
            $fail('must be a boolean');
        }
    }

    public function schema(): array
    {
        return ['type' => 'boolean'];
    }
}
