<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Filter\UnsupportedFilterOperatorException;
use Tobyz\JsonApiServer\Schema\Filter;
use Tobyz\JsonApiServer\Schema\Type;

class Where extends Filter
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

    protected string|Expression|array|Closure|null $column = null;
    protected bool $asBoolean = false;
    private bool $operatorsConfigured = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        parent::operators($this->defaultOperators());
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * @param string|Expression|array{0: string|Expression, 1: array}|Closure|null $column
     */
    public function column(string|Expression|array|Closure|null $column): static
    {
        $this->column = $column;

        return $this;
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
        [$column, $bindings] = $this->getColumn($query, $context);

        if ($this->asBoolean) {
            $this->addColumnBindings($query, $bindings);
            $query->where($column, $value);
            return;
        }

        foreach ($value as $operator => $v) {
            if (!in_array($operator, static::SUPPORTED_OPERATORS, true)) {
                throw new UnsupportedFilterOperatorException($operator);
            }

            $this->addColumnBindings($query, $bindings);

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
            }
        }
    }

    /**
     * @return array{0: string|Expression, 1: array}
     */
    protected function getColumn(object $query, Context $context): array
    {
        $column = $this->column;

        if ($column instanceof Closure) {
            $column = $column($context);
        }

        if (is_array($column)) {
            return $this->normalizeColumnTuple($column);
        }

        if (is_string($column) || $column instanceof Expression) {
            return [$column, []];
        }

        if ($column !== null) {
            throw new InvalidArgumentException(
                'Column expressions must resolve to a column or [column, bindings].',
            );
        }

        return [Str::snake($this->name), []];
    }

    protected function addColumnBindings(object $query, array $bindings): void
    {
        if (!$bindings) {
            return;
        }

        if (!is_callable([$query, 'addBinding'])) {
            throw new LogicException('Query builders must support addBinding() to use bound column expressions.');
        }

        $query->addBinding($bindings, 'where');
    }

    /**
     * @param array<mixed> $column
     * @return array{0: string|Expression, 1: array}
     */
    private function normalizeColumnTuple(array $column): array
    {
        [$expression, $bindings] = $column + [null, null];

        if ((!is_string($expression) && !$expression instanceof Expression) || !is_array($bindings)) {
            throw new InvalidArgumentException(
                'Column expression arrays must be [string|Expression, bindings array].',
            );
        }

        if (array_filter($bindings, 'is_array')) {
            throw new InvalidArgumentException('Column expression bindings must be a flat array.');
        }

        return [$this->rawExpression($expression), array_values($bindings)];
    }

    private function rawExpression(string|Expression $expression): string|Expression
    {
        $expressionClass = 'Illuminate\\Database\\Query\\Expression';

        return is_string($expression) &&
            class_exists($expressionClass) &&
            is_subclass_of($expressionClass, Expression::class)
                ? new $expressionClass($expression)
                : $expression;
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
