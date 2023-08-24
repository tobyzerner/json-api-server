<?php

namespace Tobyz\JsonApiServer\Schema\Field;

class Boolean extends Attribute
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->serialize(static fn($value) => (bool) $value);

        $this->validate(static function (mixed $value, callable $fail): void {
            if (!is_bool($value) && $value !== null) {
                $fail('must be a boolean');
            }
        });
    }

    public function getSchema(): array
    {
        return parent::getSchema() + ['type' => 'boolean'];
    }
}
