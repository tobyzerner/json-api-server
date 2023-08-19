<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use LogicException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Filter;

use function Tobyz\JsonApiServer\apply_filters;

class WhereHas extends Filter
{
    public static function make(string $name): static
    {
        return new static($name);
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        $value = (array) $value;
        $field = $context->fields($context->resource)[$this->name] ?? null;

        if (!$field instanceof Relationship || count($field->types) !== 1) {
            throw new LogicException(
                'The WhereHas filter must have a corresponding non-polymorphic relationship field',
            );
        }

        $relatedResource = $context->resource($field->types[0]);

        $query->whereHas($field->property ?: $field->name, function ($query) use (
            $value,
            $relatedResource,
            $context,
        ) {
            if (array_is_list($value)) {
                $query->whereKey(
                    array_merge(...array_map(fn($v) => explode(',', $v), (array) $value)),
                );
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
