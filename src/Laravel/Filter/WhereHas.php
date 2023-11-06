<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use LogicException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Filter;

use function Tobyz\JsonApiServer\apply_filters;

class WhereHas extends Filter
{
    public Relationship|string|null $field = null;

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
        $value = (array) $value;
        $field =
            $this->field instanceof Relationship
                ? $this->field
                : $context->fields($context->resource)[$this->field ?: $this->name] ?? null;

        if (!$field instanceof Relationship || count($field->types) !== 1) {
            throw new LogicException(
                'The WhereHas filter must have a non-polymorphic relationship field',
            );
        }

        $relatedResource = $context->resource($field->types[0]);

        $query->whereHas($field->property ?: $field->name, function ($query) use (
            $value,
            $relatedResource,
            $context,
        ) {
            if (array_is_list($value)) {
                $query->whereKey(array_merge(...array_map(fn($v) => explode(',', $v), $value)));
            } else {
                apply_filters(
                    $query,
                    $value,
                    $relatedResource,
                    $context->withResource($relatedResource),
                );
            }
        });
    }
}
