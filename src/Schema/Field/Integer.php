<?php

namespace Tobyz\JsonApiServer\Schema\Field;

class Integer extends Number
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->serialize(static fn($value) => (int) $value);

        $this->validate(static function (mixed $value, callable $fail): void {
            if (is_numeric($value) && !is_int($value)) {
                $fail('must be an integer');
            }
        });
    }

    public function getSchema(): array
    {
        return ['type' => 'integer'] + parent::getSchema();
    }
}
