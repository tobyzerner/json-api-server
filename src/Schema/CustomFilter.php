<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use Tobyz\JsonApiServer\Context;

class CustomFilter extends Filter
{
    private Closure $apply;

    public function __construct(string $name, ?Closure $apply = null)
    {
        parent::__construct($name);

        $this->apply = $apply ?? fn() => null;
    }

    public static function make(string $name, ?Closure $apply = null): static
    {
        return new static($name, $apply);
    }

    public function filter(Closure $apply): static
    {
        $this->apply = $apply;

        return $this;
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        ($this->apply)($query, $value, $context);
    }
}
