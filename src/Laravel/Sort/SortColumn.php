<?php

namespace Tobyz\JsonApiServer\Laravel\Sort;

use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Sort;

class SortColumn extends Sort
{
    protected ?string $column = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function column(?string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function apply(object $query, string $direction, Context $context): void
    {
        $column = $this->column ?: Str::snake($this->name);

        $query->orderBy($column, $direction);
    }
}
