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

        $this->deserialize(
            static fn($value) => is_string($value)
                ? \DateTime::createFromFormat(DateTimeInterface::RFC3339, $value)
                : $value,
        );

        $this->validate(static function (mixed $value, callable $fail): void {
            if (!\DateTime::createFromFormat(DateTimeInterface::RFC3339, $value)) {
                $fail('must be a date-time');
            }
        });
    }
}
