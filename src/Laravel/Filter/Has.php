<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;

class Has extends Filter
{
    public ?string $relation = null;
    public ?Closure $scope = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function relation(?string $relation): static
    {
        $this->relation = $relation;

        return $this;
    }

    public function scope(?Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas($this->relation ?: $this->name, $this->scope);
        } else {
            $query->whereDoesntHave($this->relation ?: $this->name, $this->scope);
        }
    }
}
