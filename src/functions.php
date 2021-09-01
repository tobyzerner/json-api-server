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
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Tobyz\JsonApiServer\Schema\Field;

function json_api_response($document, int $status = 200): Response
{
    return (new Response($status))
        ->withHeader('Content-Type', JsonApi::MEDIA_TYPE)
        ->withBody(Stream::create(json_encode($document, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES)));
}

function negate(Closure $condition): Closure
{
    return function (...$args) use ($condition) {
        return ! $condition(...$args);
    };
}

function wrap($value): Closure
{
    if (! $value instanceof Closure) {
        $value = function () use ($value) {
            return $value;
        };
    }

    return $value;
}

function evaluate($condition, array $params): bool
{
    return $condition === true || (is_callable($condition) && $condition(...$params));
}

function run_callbacks(array $callbacks, array $params): void
{
    foreach ($callbacks as $callback) {
        $callback(...$params);
    }
}

function has_value(array $data, Field $field): bool
{
    return array_key_exists($location = $field->getLocation(), $data)
        && array_key_exists($field->getName(), $data[$location]);
}

function get_value(array $data, Field $field)
{
    return $data[$field->getLocation()][$field->getName()] ?? null;
}

function set_value(array &$data, Field $field, $value): void
{
    $data[$field->getLocation()][$field->getName()] = $value;
}
