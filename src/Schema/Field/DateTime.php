<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use DateTimeInterface;

class DateTime extends Attribute
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->serialize(
            static fn($value) => $value instanceof DateTimeInterface
                ? $value->format(DateTimeInterface::RFC3339)
                : $value,
        );

        $this->deserialize(static function ($value) {
            if (
                is_string($value) &&
                ($date = \DateTime::createFromFormat(DateTimeInterface::RFC3339, $value))
            ) {
                return $date;
            }
            return $value;
        });

        $this->validate(static function (mixed $value, callable $fail): void {
            if (!$value instanceof \DateTime) {
                $fail('must be a date-time');
            }
        });
    }
}
