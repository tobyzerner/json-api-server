<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use DateTimeInterface;
use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;

class DateTime extends AbstractType
{
    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface
            ? $value->format(DateTimeInterface::RFC3339)
            : $value;
    }

    protected function deserializeValue(mixed $value): mixed
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

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (!$value instanceof \DateTime) {
            $fail(new TypeMismatchException('date-time', gettype($value)));
        }
    }

    protected function getSchema(): array
    {
        return ['type' => 'string', 'format' => 'date-time'];
    }
}
