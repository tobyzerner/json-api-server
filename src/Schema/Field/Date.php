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

        $this->deserialize(static function ($value) {
            if (
                is_string($value) &&
                ($date = \DateTime::createFromFormat(static::FORMAT, $value))
            ) {
                return $date->setTime(0, 0);
            }
            return $value;
        });

        $this->validate(static function (mixed $value, callable $fail): void {
            if (!$value instanceof \DateTime) {
                $fail('must be a date');
            }
        });
    }
}
