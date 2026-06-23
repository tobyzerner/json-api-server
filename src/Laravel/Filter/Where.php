<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Filter\UnsupportedFilterOperatorException;
use Tobyz\JsonApiServer\Schema\Type;

class Where extends ColumnFilter
{
    use SupportsOperators {
        operators as private setOperators;
    }

    private const COMPARISON_OPERATORS = ['eq', 'ne', 'in', 'notin', 'lt', 'lte', 'gt', 'gte'];
    private const NULL_OPERATORS = ['null', 'notnull'];

    public const SUPPORTED_OPERATORS = [
        ...self::COMPARISON_OPERATORS,
        ...self::NULL_OPERATORS,
        'like',
        'notlike',
    ];

    private const DEFAULT_OPERATORS_BY_TYPE = [
        Type\Boolean::class => ['eq', 'ne', ...self::NULL_OPERATORS],
        Type\Date::class => [...self::COMPARISON_OPERATORS, ...self::NULL_OPERATORS],
        Type\DateTime::class => [...self::COMPARISON_OPERATORS, ...self::NULL_OPERATORS],
        Type\Number::class => [...self::COMPARISON_OPERATORS, ...self::NULL_OPERATORS],
    ];

    protected bool $asBoolean = false;
    private bool $operatorsConfigured = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        parent::operators($this->defaultOperators());
    }

    protected function defaultOperators(): array
    {
        $type = $this->type instanceof Type\Arr ? $this->type->items : $this->type;

        foreach (self::DEFAULT_OPERATORS_BY_TYPE as $class => $operators) {
            if ($type instanceof $class) {
                return array_values(array_intersect(static::SUPPORTED_OPERATORS, $operators));
            }
        }

        return static::SUPPORTED_OPERATORS;
    }

    public function type(Type\Type $type): static
    {
        parent::type($type);

        if (!$this->operatorsConfigured) {
            parent::operators($this->defaultOperators());
        }

        return $this;
    }

    public function operators(array $only): static
    {
        $this->setOperators($only);
        $this->operatorsConfigured = true;

        return $this;
    }

    public function asBoolean(): static
    {
        $this->asBoolean = true;

        return $this->type(Type\Boolean::make())->operators([]);
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
