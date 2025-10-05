<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use DateTimeInterface;
use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;

class Date extends AbstractType
{
    private const FORMAT = 'Y-m-d';

    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value instanceof DateTimeInterface ? $value->format(static::FORMAT) : $value;
    }

    protected function deserializeValue(mixed $value): mixed
    {
        if (is_string($value) && ($date = \DateTime::createFromFormat(static::FORMAT, $value))) {
            return $date->setTime(0, 0);
        }

        return $value;
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (!$value instanceof \DateTime) {
            $fail(new TypeMismatchException('date', gettype($value)));
        }
    }

    protected function getSchema(): array
    {
        return ['type' => 'string', 'format' => 'date'];
    }
}
