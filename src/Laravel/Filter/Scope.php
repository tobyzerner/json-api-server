<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;

class Scope extends Filter
{
    protected null|string|Closure $scope = null;
    protected bool $asBoolean = false;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function scope(null|string|Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function asBoolean(bool $asBoolean = true): static
    {
        $this->asBoolean = $asBoolean;

        return $this;
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        $scope = $this->scope ?: $this->name;

        if (is_string($scope)) {
            $scope = fn($query, ...$args) => $query->$scope(...$args);
        } else {
            $scope = fn($query, ...$args) => $query->where(fn($query) => $scope($query, ...$args));
        }

        if ($this->asBoolean) {
            if (filter_var($value, FILTER_VALIDATE_BOOL)) {
                $scope($query);
            } else {
                $query->whereNot(fn($query) => $scope($query));
            }
        } else {
            $scope($query, $value);
        }
    }
}
