<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use Tobyz\JsonApiServer\Context;

class CustomFilter extends Filter
{
    public function __construct(string $name, private readonly Closure $apply)
    {
        parent::__construct($name);
    }

    public static function make(string $name, Closure $apply): static
    {
        return new static($name, $apply);
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        ($this->apply)($query, $value, $context);
    }
}
