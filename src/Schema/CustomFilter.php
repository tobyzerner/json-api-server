<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use Tobyz\JsonApiServer\Context;

class CustomFilter extends Filter
{
    public function __construct(public string $name, private readonly Closure $apply)
    {
        parent::__construct($name);
    }

    public static function make(string $name, Closure $apply): static
    {
        return new static($name, $apply);
    }

    public function apply(object $query, string|array $value, Context $context): void
    {
        ($this->apply)($query, $value, $context);
    }
}
