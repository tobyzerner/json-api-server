<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Closure;

class Integer extends Attribute
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->serialize(static fn($value) => (int) $value);

        $this->validate(static function (mixed $value, Closure $fail): void {
            if (!is_int($value)) {
                $fail('must be an integer');
            }
        });
    }
}
