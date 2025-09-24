<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Schema\Filter;

abstract class EloquentFilter extends Filter
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

    protected function getColumn(): string
    {
        return $this->column ?: Str::snake($this->name);
    }
}
