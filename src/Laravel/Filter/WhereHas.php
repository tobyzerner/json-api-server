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
    protected const QUERY_BUILDER_METHOD = 'whereHas';

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
        $resource = $context->collection;

        if (!$resource instanceof EloquentResource) {
            throw new LogicException(
                sprintf('The %s filter can only be used for Eloquent resources', get_class($this)),
            );
        }

        $value = (array) $value;
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

        $query->{static::QUERY_BUILDER_METHOD}($field->property ?: $field->name, function (
            $query,
        ) use ($value, $relatedCollection, $context) {
            if ($relatedCollection instanceof EloquentResource) {
                $relatedCollection->scope($query, $context);
            }

            if (array_is_list($value)) {
                $query->whereKey(array_merge(...array_map(fn($v) => explode(',', $v), $value)));
            } else {
                apply_filters(
                    $query,
                    $value,
                    $relatedCollection,
                    $context->withCollection($relatedCollection),
                );
            }
        });
    }
}
