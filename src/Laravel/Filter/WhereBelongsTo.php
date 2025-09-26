<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;

class WhereBelongsTo extends Filter
{
    use SupportsOperators;

    public const SUPPORTED_OPERATORS = ['eq', 'in', 'ne', 'notin', 'null', 'notnull'];

    protected ?string $relationship = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function relationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        $relationship = $query->getModel()->{$this->relationship ?: $this->name}();
        $column = $relationship->getQualifiedForeignKeyName();

        Where::make($this->name)
            ->column($column)
            ->operators($this->operators)
            ->commaSeparated()
            ->apply($query, $value, $context);
    }
}
