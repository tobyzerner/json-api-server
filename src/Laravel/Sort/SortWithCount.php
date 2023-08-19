<?php

namespace Tobyz\JsonApiServer\Laravel\Sort;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Sort;

class SortWithCount extends Sort
{
    protected ?string $relationship = null;
    protected ?Closure $scope = null;
    protected ?string $countAs = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function relationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function scope(?Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function countAs(?string $countAs): static
    {
        $this->countAs = $countAs;

        return $this;
    }

    public function apply(object $query, string $direction, Context $context): void
    {
        $relationship = $this->relationship ?: $this->name;
        $countAs = $this->countAs ?: "{$relationship}_count";

        if ($countAs) {
            $relationship .= " as $countAs";
        }

        $query->withCount([$relationship => $this->scope])->orderBy($countAs, $direction);
    }
}
