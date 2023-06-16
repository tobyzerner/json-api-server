<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use DateTimeInterface;

class Date extends Attribute
{
    private const FORMAT = 'Y-m-d';

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->serialize(
            static fn($value) => $value instanceof DateTimeInterface
                ? $value->format(static::FORMAT)
                : $value,
        );

        $this->deserialize(
            static fn($value) => is_string($value)
                ? \DateTime::createFromFormat(static::FORMAT, $value)->setTime(0, 0)
                : $value,
        );

        $this->validate(static function (mixed $value, callable $fail): void {
            if (!\DateTime::createFromFormat(static::FORMAT, $value)) {
                $fail('must be a date');
            }
        });
    }
}
