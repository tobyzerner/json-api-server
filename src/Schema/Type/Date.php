<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use DateTimeInterface;

class Date implements Type
{
    private const FORMAT = 'Y-m-d';

    public static function make(): static
    {
        return new static();
    }

    public function serialize(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format(static::FORMAT) : $value;
    }

    public function deserialize(mixed $value): mixed
    {
        if (is_string($value) && ($date = \DateTime::createFromFormat(static::FORMAT, $value))) {
            return $date->setTime(0, 0);
        }

        return $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        if (!$value instanceof \DateTime) {
            $fail('must be a date');
        }
    }

    public function schema(): array
    {
        return ['type' => 'string', 'format' => 'date'];
    }
}
