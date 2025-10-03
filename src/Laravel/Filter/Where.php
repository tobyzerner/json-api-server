<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;

class Where extends ColumnFilter
{
    use SupportsOperators;

    public const SUPPORTED_OPERATORS = [
        'eq',
        'ne',
        'in',
        'notin',
        'lt',
        'lte',
        'gt',
        'gte',
        'like',
        'notlike',
        'null',
        'notnull',
    ];

    protected bool $asBoolean = false;
    protected bool $commaSeparated = false;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function asBoolean(): static
    {
        $this->asBoolean = true;

        return $this;
    }

    public function commaSeparated(): static
    {
        $this->commaSeparated = true;

        return $this;
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        if ($this->asBoolean) {
            $query->where($this->getColumn(), filter_var($value, FILTER_VALIDATE_BOOLEAN));
            return;
        }

        $value = $this->resolveOperators($value);

        foreach ($value as $operator => $v) {
            switch ($operator) {
                case 'eq':
                case 'in':
                    $this->applyEquals($query, $v);
                    break;

                case 'ne':
                case 'notin':
                    $this->applyNotEquals($query, $v);
                    break;

                case 'lt':
                case 'lte':
                case 'gt':
                case 'gte':
                    $this->applyComparison($query, $operator, $v);
                    break;

                case 'like':
                    $this->applyLike($query, $v);
                    break;

                case 'notlike':
                    $this->applyNotLike($query, $v);
                    break;

                case 'null':
                case 'notnull':
                    $this->applyNull(
                        $query,
                        $operator === 'null' xor !filter_var($v, FILTER_VALIDATE_BOOLEAN),
                    );
                    break;

                default:
                    throw new BadRequestException(
                        $context->translate('laravel.filter.unsupported_operator', [
                            'operator' => $operator,
                        ]),
                    );
            }
        }
    }

    private function splitCommaSeparated(array|string $value): array|string
    {
        if ($this->commaSeparated && is_string($value)) {
            return explode(',', $value);
        }

        return $value;
    }

    private function applyEquals(object $query, array|string $value): void
    {
        $value = $this->splitCommaSeparated($value);

        $query->whereIn($this->getColumn(), (array) $value);
    }

    private function applyNotEquals(object $query, array|string $value): void
    {
        $value = $this->splitCommaSeparated($value);

        $query->whereNotIn($this->getColumn(), (array) $value);
    }

    private function applyComparison(object $query, string $operator, array|string $value): void
    {
        $value = $this->firstValue($value);

        $query->where(
            $this->getColumn(),
            ['lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>='][$operator],
            $value,
        );
    }

    private function applyLike(object $query, array|string $value): void
    {
        $value = $this->firstValue($value);

        $query->where($this->getColumn(), 'like', $value);
    }

    private function applyNotLike(object $query, array|string $value): void
    {
        $value = $this->firstValue($value);

        $query->where($this->getColumn(), 'not like', $value);
    }

    private function applyNull(object $query, bool $value): void
    {
        $query->{$value ? 'whereNull' : 'whereNotNull'}($this->getColumn());
    }

    private function firstValue(array|string $value): mixed
    {
        if (is_array($value)) {
            return $value[0] ?? null;
        }

        return $value;
    }
}
