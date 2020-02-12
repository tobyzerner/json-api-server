<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    return array_key_exists($location = $field->getLocation(), $data)
        && array_key_exists($field->getName(), $data[$location]);
}

function get_value(array $data, Field $field)
{
    return $data[$field->getLocation()][$field->getName()] ?? null;
}

function set_value(array &$data, Field $field, $value)
{
    $data[$field->getLocation()][$field->getName()] = $value;
}
