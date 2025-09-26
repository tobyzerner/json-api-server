<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use LogicException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Filter;

use function Tobyz\JsonApiServer\apply_filters;

class WhereHas extends Filter
{
    use SupportsOperators;

    public Relationship|string|null $field = null;

    public const SUPPORTED_OPERATORS = ['eq', 'in', 'ne', 'notin', 'null', 'notnull'];

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function field(Relationship|string|null $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function apply(object $query, array|string $value, Context $context): void
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

        $value = $this->resolveOperators($value);

        foreach ($value as $operator => $v) {
            $method = in_array($operator, ['ne', 'notin', 'null']) ? 'whereDoesntHave' : 'whereHas';

            $query->{$method}($field->property ?: $field->name, function ($query) use (
                $operator,
                $v,
                $relatedCollection,
                $context,
            ) {
                if ($relatedCollection instanceof EloquentResource) {
                    $relatedCollection->scope($query, $context);
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

    private function extractIds(array|string $value): ?array
    {
        if (is_string($value)) {
            return explode(',', $value);
        }

        if (array_is_list($value)) {
            return $value;
        }

        return null;
    }
}
