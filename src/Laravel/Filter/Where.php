<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Filter\UnsupportedFilterOperatorException;
use Tobyz\JsonApiServer\Schema\Type;

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

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->operators(static::SUPPORTED_OPERATORS);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function asBoolean(): static
    {
        $this->asBoolean = true;

        return $this->type(Type\Boolean::make())->operators([]);
    }

    public function commaSeparated(?Type\Type $items = null): static
    {
        if ($items === null && $this->type instanceof Type\Arr) {
            $this->type->commaSeparated();

            return $this;
        }

        return $this->type(Type\Arr::make()->items($items ?? $this->type)->commaSeparated());
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        $column = $this->getColumn($query);

        if ($this->asBoolean) {
            $query->where($column, $value);
            return;
        }

        foreach ($value as $operator => $v) {
            switch ($operator) {
                case 'eq':
                case 'in':
                    $query->whereIn($column, $this->arrayValue($v));
                    break;

                case 'ne':
                case 'notin':
                    $query->whereNotIn($column, $this->arrayValue($v));
                    break;

                case 'lt':
                case 'lte':
                case 'gt':
                case 'gte':
                    $query->where(
                        $column,
                        ['lt' => '<', 'lte' => '<=', 'gt' => '>', 'gte' => '>='][$operator],
                        $this->firstValue($v),
                    );
                    break;

                case 'like':
                case 'notlike':
                    $query->where(
                        $column,
                        $operator === 'like' ? 'like' : 'not like',
                        $this->firstValue($v),
                    );
                    break;

                case 'null':
                case 'notnull':
                    $matchesNull = $operator === 'null' ? $v : !$v;

                    $query->{$matchesNull ? 'whereNull' : 'whereNotNull'}($column);
                    break;

                default:
                    throw new UnsupportedFilterOperatorException($operator);
            }
        }
    }

    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [$value];
    }

    private function firstValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value[0] ?? null;
        }

        return $value;
    }
}
