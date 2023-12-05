<?php

namespace Tobyz\JsonApiServer;

use Closure;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

function json_api_response($document, int $status = 200): Response
{
    return (new Response($status))
        ->withHeader('Content-Type', JsonApi::MEDIA_TYPE)
        ->withBody(
            Stream::create(
                json_encode(
                    $document + ['jsonapi' => ['version' => JsonApi::VERSION]],
                    JSON_HEX_TAG |
                        JSON_HEX_APOS |
                        JSON_HEX_AMP |
                        JSON_HEX_QUOT |
                        JSON_UNESCAPED_SLASHES,
                ),
            ),
        );
}

function negate(bool|Closure $condition): bool|Closure
{
    if (is_bool($condition)) {
        return !$condition;
    }

    return fn(...$args) => !$condition(...$args);
}

function location(Field $field): string
{
    return $field instanceof Relationship ? 'relationships' : 'attributes';
}

function has_value(array $data, Field $field): bool
{
    $location = location($field);

    return array_key_exists($location, $data) && array_key_exists($field->name, $data[$location]);
}

function get_value(array $data, Field $field)
{
    return $data[location($field)][$field->name] ?? null;
}

function set_value(array &$data, Field $field, $value): void
{
    $data[location($field)][$field->name] = $value;
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
    $context = $context->withCollection($collection);
    $availableFilters = $collection->filters();

    foreach ($filters as $name => $value) {
        foreach ($availableFilters as $filter) {
            if ($filter->name === $name && $filter->isVisible($context)) {
                $filter->apply($query, $value, $context);
                continue 2;
            }
        }

        throw (new BadRequestException("Invalid filter: $name"))->setSource([
            'parameter' => "[$name]",
        ]);
    }
}
