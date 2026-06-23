<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Filter\InvalidFilterValueException;
use Tobyz\JsonApiServer\Schema\Filter;

class WhereCount extends Filter
{
    use UsesRelationship;
    use SupportsOperators;

    protected const SUPPORTED_OPERATORS = ['eq', 'ne', 'gt', 'lt', 'lte', 'gte'];

    private const OPERATOR_MAP = [
        'eq' => '=',
        'ne' => '!=',
        'gt' => '>',
        'lt' => '<',
        'lte' => '<=',
        'gte' => '>=',
    ];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->operators(static::SUPPORTED_OPERATORS);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        foreach ($value as $operator => $val) {
            if (!is_scalar($val)) {
                throw new InvalidFilterValueException();
            }

            $query->whereHas(
                $this->relationship ?: $this->name,
                $this->scope ? fn($query) => ($this->scope)($query, $context) : null,
                static::OPERATOR_MAP[$operator],
                $val,
            );
        }
    }
}
