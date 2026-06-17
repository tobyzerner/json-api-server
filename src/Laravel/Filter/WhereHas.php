<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use LogicException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Laravel\Field\ToMany;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Filter;

use function Tobyz\JsonApiServer\apply_filters;

class WhereHas extends Filter
{
    use SupportsOperators;

    public Relationship|string|null $field = null;

    public const SUPPORTED_OPERATORS = ['eq', 'in', 'ne', 'notin', 'null', 'notnull'];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->operators(static::SUPPORTED_OPERATORS);
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function field(Relationship|string|null $field): static
    {
        $this->field = $field;

        return $this;
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        $resource = $context->collection;

        if (!$resource instanceof EloquentResource) {
            throw new LogicException(
                sprintf('The %s filter can only be used for Eloquent resources', get_class($this)),
            );
        }

        $field =
            $this->field instanceof Relationship
                ? $this->field
                : $context->fields($resource)[$this->field ?: $this->name] ?? null;

        if (!$field instanceof Relationship || count($field->collections) !== 1) {
            throw new LogicException(
                sprintf(
                    'The %s filter must have a non-polymorphic relationship field',
                    get_class($this),
                ),
            );
        }

        $relatedCollection = $context->api->getCollection($field->collections[0]);

        foreach ($value as $operator => $v) {
            $method = match ($operator) {
                'ne', 'notin' => 'whereDoesntHave',
                'null', 'notnull' => ($operator === 'null' xor
                !filter_var($v, FILTER_VALIDATE_BOOLEAN))
                    ? 'whereDoesntHave'
                    : 'whereHas',
                default => 'whereHas',
            };

            $query->{$method}($field->property ?: $field->name, function ($query) use (
                $operator,
                $v,
                $relatedCollection,
                $field,
                $context,
            ) {
                if ($relatedCollection instanceof EloquentResource) {
                    $relatedCollection->scope($query, $context);
                }

                if (($field instanceof ToMany || $field instanceof ToOne) && $field->scope) {
                    ($field->scope)($query, $context);
                }

                if (in_array($operator, ['null', 'notnull'])) {
                    return;
                }

                if ($ids = $this->extractIds($v)) {
                    $query->whereKey($ids);
                    return;
                }

                apply_filters(
                    $query,
                    (array) $v,
                    $relatedCollection,
                    $context->withCollection($relatedCollection),
                );
            });
        }
    }

    private function extractIds(mixed $value): ?array
    {
        if (is_string($value)) {
            return explode(',', $value);
        }

        if (is_scalar($value)) {
            return [$value];
        }

        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        return null;
    }

    protected function operatorDefaultValueSchema(
        array $defaultValueSchema,
        array $operatorSchema,
    ): array {
        if ($defaultValueSchema === []) {
            return ['not' => $operatorSchema];
        }

        return parent::operatorDefaultValueSchema($defaultValueSchema, $operatorSchema);
    }

    protected function isOperatorValue(mixed $value, array $operators): bool
    {
        // Relation filters may receive nested filter objects at the operator level;
        // only treat the value as operators when every top-level key is supported.
        return is_array($value) &&
            !array_is_list($value) &&
            array_diff(array_keys($value), $operators) === [];
    }
}
