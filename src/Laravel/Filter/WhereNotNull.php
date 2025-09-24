<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;

class WhereNotNull extends EloquentFilter
{
    public static function make(string $name): static
    {
        return new static($name);
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNotNull($this->getColumn());
        } else {
            $query->whereNull($this->getColumn());
        }
    }
}
