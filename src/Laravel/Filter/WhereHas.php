<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\Concerns\QueriesRelationExistence;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Laravel\Field\ToMany;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Filter;

use function Tobyz\JsonApiServer\apply_filters;

class WhereHas extends Filter
{
    use QueriesRelationExistence;
    use SupportsOperators;

    protected const SUPPORTED_OPERATORS = ['eq', 'in', 'ne', 'notin', 'null', 'notnull'];

    public Relationship|string|null $field = null;

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

        $field = $this->field instanceof Relationship
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

        $name = $field->property ?: $field->name;
        $relatedCollection = $context->api->getCollection($field->collections[0]);
        $relatedContext = $context->withField($field);

        foreach ($value as $operator => $v) {
            $exists = match ($operator) {
                'ne', 'notin' => false,
                'null', 'notnull' => $operator === 'null'
                    xor filter_var($v, FILTER_VALIDATE_BOOLEAN),
                default => true,
            };

            $ids = in_array($operator, ['null', 'notnull']) ? [] : $this->extractIds($v);

            $this->whereRelationExists(
                $query,
                $name,
                $exists,
                function (Builder $query) use (
                    $ids,
                    $v,
                    $relatedCollection,
                    $field,
                    $context,
                    $relatedContext,
                ) {
                    if ($relatedCollection instanceof EloquentResource) {
                        $relatedCollection->scopeRelated($query, $field, $relatedContext);
                    }

                    if (($field instanceof ToMany || $field instanceof ToOne) && $field->scope) {
                        ($field->scope)($query, $relatedContext);
                    }

                    if ($ids === null) {
                        apply_filters(
                            $query,
                            (array) $v,
                            $relatedCollection,
                            $context->withCollection($relatedCollection),
                        );
                    }
                },
                $ids
                    ? function (Builder $query) use ($ids, $relatedCollection, $context) {
                        $resource = $context->resourceForModel(
                            [$relatedCollection],
                            $query->getModel(),
                        );

                        if ($resource instanceof EloquentResource) {
                            $resource->whereIds($query, $ids);
                        } else {
                            $query->whereKey($ids);
                        }
                    } : null,
            );
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
        return (
            is_array($value)
            && !array_is_list($value)
            && array_diff(array_keys($value), $operators) === []
        );
    }
}
