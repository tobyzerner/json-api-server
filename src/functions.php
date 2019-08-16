<?php

namespace Tobyz\JsonApiServer;

use Closure;
use Tobyz\JsonApiServer\Schema\Field;

function negate(Closure $condition)
{
    return function (...$args) use ($condition) {
        return ! $condition(...$args);
    };
}

function wrap($value)
{
    if (! $value instanceof Closure) {
        $value = function () use ($value) {
            return $value;
        };
    }

    return $value;
}

function evaluate($condition, array $params)
{
    return $condition === true || ($condition instanceof Closure && $condition(...$params));
}

function run_callbacks(array $callbacks, array $params)
{
    foreach ($callbacks as $callback) {
        $callback(...$params);
    }
}

function has_value(array $data, Field $field)
{
    return isset($data[$field->getLocation()][$field->getName()]);
}

function &get_value(array $data, Field $field)
{
    return $data[$field->getLocation()][$field->getName()];
}

function set_value(array &$data, Field $field, $value)
{
    $data[$field->getLocation()][$field->getName()] = $value;
}

function array_set(array $array, $key, $value)
{
    $keys = explode('.', $key);

    while (count($keys) > 1) {
        $array = &$array[array_shift($keys)];
    }

    $array[array_shift($keys)] = $value;

    return $array;
}
