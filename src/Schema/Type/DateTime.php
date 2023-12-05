<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use DateTimeInterface;

class DateTime implements Type
{
    public static function make(): static
    {
        return new static();
    }

    public function serialize(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DateTimeInterface::RFC3339)
            : $value;
    }

    public function deserialize(mixed $value): mixed
    {
        if (
            is_string($value) &&
            (($date = \DateTime::createFromFormat(DateTimeInterface::RFC3339, $value)) ||
                ($date = \DateTime::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $value)))
        ) {
            return $date;
        }

        return $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        if (!$value instanceof \DateTime) {
            $fail('must be a date-time');
        }
    }

    public function schema(): array
    {
        return ['type' => 'string', 'format' => 'date-time'];
    }
}
