<?php

namespace Tobscure\JsonApiServer\Schema;

use Closure;

class Meta
{
    public $name;
    public $value;

    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->value = $this->wrap($value);
    }

    private function wrap($value)
    {
        if (! $value instanceof Closure) {
            $value = function () use ($value) {
                return $value;
            };
        }

        return $value;
    }
}
