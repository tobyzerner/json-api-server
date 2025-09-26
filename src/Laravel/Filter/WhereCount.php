<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;

class WhereCount extends Filter
{
    use UsesRelationship;
    use SupportsOperators;

    public const SUPPORTED_OPERATORS = ['eq', 'ne', 'gt', 'lt', 'lte', 'gte'];

    private const OPERATOR_MAP = [
        'eq' => '=',
        'ne' => '!=',
        'gt' => '>',
        'lt' => '<',
        'lte' => '<=',
        'gte' => '>=',
    ];

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        foreach ($this->resolveOperators($value) as $operator => $val) {
            $query->whereHas(
                $this->relationship ?: $this->name,
                $this->scope ? fn($query) => ($this->scope)($query, $context) : null,
                static::OPERATOR_MAP[$operator],
                $val,
            );
        }
    }
}
