<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Schema\Filter;

abstract class EloquentFilter extends Filter
{
    protected ?string $column = null;
    protected bool $asBoolean = false;

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

    public function asBoolean(): static
    {
        $this->asBoolean = true;

        return $this;
    }

    protected function parseValue(string|array $value): string|array
    {
        if ($this->asBoolean) {
            if (is_array($value)) {
                return array_map($this->parseValue(...), $value);
            }

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }
}
