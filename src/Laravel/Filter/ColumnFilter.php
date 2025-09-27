<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Schema\Filter;

abstract class ColumnFilter extends Filter
{
    protected string|Expression|null $column = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function column(string|Expression|null $column): static
    {
        $this->column = $column;

        return $this;
    }

    protected function getColumn(): string|Expression
    {
        return $this->column ?: Str::snake($this->name);
    }
}
