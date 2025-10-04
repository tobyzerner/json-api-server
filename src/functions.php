<?php

namespace Tobyz\JsonApiServer;

use Closure;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Field\Field;

function negate(bool|Closure $condition): bool|Closure
{
    if (is_bool($condition)) {
        return !$condition;
    }

    return fn(...$args) => !$condition(...$args);
}

function field_path(Field $field): string
{
    return '/' . implode('/', array_filter([$field::location(), $field->name]));
}

function has_value(array $data, Field $field): bool
{
    if ($location = $field::location()) {
        $data = $data[$location] ?? [];
    }

    return array_key_exists($field->name, $data);
}

function get_value(array $data, Field $field)
{
    if ($location = $field::location()) {
        $data = $data[$location] ?? [];
    }

    return $data[$field->name] ?? null;
}

function set_value(array &$data, Field $field, $value): void
{
    if ($location = $field::location()) {
        $data = &$data[$location];
    }

    $data[$field->name] = $value;
}

function resolve_value(mixed $value): mixed
{
    while ($value instanceof Closure) {
        $value = $value();
    }

    return $value;
}

function parse_sort_string(string $string): array
{
    return array_map(function ($field) {
        if ($field[0] === '-') {
            return [substr($field, 1), 'desc'];
        } else {
            return [$field, 'asc'];
        }
    }, explode(',', $string));
}

function apply_filters(
    $query,
    array $filters,
    Collection&Listable $collection,
    Context $context,
): void {
    (new Filterer($collection, $context))->apply($query, $filters);
}
