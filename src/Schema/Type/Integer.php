<?php

namespace Tobyz\JsonApiServer\Schema\Type;

class Integer extends Number
{
    public function serialize(mixed $value): mixed
    {
        return (int) $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        if (!is_int($value)) {
            $fail('must be an integer');
            return;
        }

        parent::validate($value, $fail);
    }

    public function schema(): array
    {
        return ['type' => 'integer'] + parent::schema();
    }
}
