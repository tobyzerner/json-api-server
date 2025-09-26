<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;

class WhereNull extends ColumnFilter
{
    public static function make(string $name): static
    {
        return new static($name);
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        Where::make($this->name)
            ->column($this->getColumn())
            ->operators(['null'])
            ->apply($query, $value, $context);
    }
}
