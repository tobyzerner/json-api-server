<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;

class Where extends EloquentFilter
{
    protected bool $asNumber = false;
    protected bool $commaSeparated = false;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function asBoolean(): static
    {
        $this->asBoolean = true;
        $this->asNumber = false;

        return $this;
    }

    public function asNumber(): static
    {
        $this->asNumber = true;
        $this->asBoolean = false;

        return $this;
    }

    public function commaSeparated(): static
    {
        $this->commaSeparated = true;

        return $this;
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        $value = $this->parseValue($value);

        if ($this->commaSeparated) {
            $value = array_merge(...array_map(fn($v) => explode(',', $v), (array) $value));
        }

        if ($this->asNumber) {
            $this->filterNumber($query, $value);
        } else {
            $query->whereIn($this->getColumn(), (array) $value);
        }
    }

    private function filterNumber(object $query, array|string $value): void
    {
        $query->where(function ($query) use ($value) {
            foreach ((array) $value as $v) {
                $query->orWhere(function ($query) use ($v) {
                    if (preg_match('/(.+)\.\.(.+)/', $v, $matches)) {
                        if ($matches[1] !== '*') {
                            $query->where($this->getColumn(), '>=', $matches[1]);
                        }
                        if ($matches[2] !== '*') {
                            $query->where($this->getColumn(), '<=', $matches[2]);
                        }
                        return;
                    }

                    foreach (['>=', '>', '<=', '<'] as $operator) {
                        if (str_starts_with($v, $operator)) {
                            $query->where(
                                $this->getColumn(),
                                $operator,
                                substr($v, strlen($operator)),
                            );
                            return;
                        }
                    }

                    $query->where($this->getColumn(), $v);
                });
            }
        });
    }
}
