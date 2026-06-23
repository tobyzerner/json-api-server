<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;
use Tobyz\JsonApiServer\Schema\Type;

class Scope extends Filter
{
    use SupportsOperators;

    protected const SUPPORTED_OPERATORS = ['eq', 'ne'];

    protected null|string|Closure $scope = null;
    protected bool $asBoolean = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->operators(static::SUPPORTED_OPERATORS);
    }

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

        if (!$asBoolean) {
            $this->type = null;

            return $this->operators(static::SUPPORTED_OPERATORS);
        }

        return $this->type(Type\Boolean::make())->operators([]);
    }

    public function commaSeparated(?Type\Type $items = null): static
    {
        if ($items === null && $this->type instanceof Type\Arr) {
            $this->type->commaSeparated();

            return $this;
        }

        return $this->type(
            Type\Arr::make()
                ->items($items ?? $this->type)
                ->commaSeparated(),
        );
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        $scope = $this->scope ?: $this->name;

        if (is_string($scope)) {
            $scope = fn($query, ...$args) => $query->$scope(...$args);
        } else {
            $scope = fn($query, ...$args) => $query->where(fn($query) => $scope($query, ...$args));
        }

        if ($this->asBoolean) {
            if ($value) {
                $scope($query);
            } else {
                $query->whereNot(fn($query) => $scope($query));
            }
            return;
        }

        foreach ($value as $operator => $val) {
            if ($operator === 'ne') {
                $query->whereNot(fn($query) => $scope($query, $val));
            } else {
                $scope($query, $val);
            }
        }
    }
}
