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

use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\Relationship;
use Tobyz\JsonApiServer\Schema\Type;

final class ResourceType
{
    private $type;
    private $adapter;
    private $buildSchema;
    private $schema;

    public function __construct(string $type, AdapterInterface $adapter, callable $buildSchema = null)
    {
        $this->type = $type;
        $this->adapter = $adapter;
        $this->buildSchema = $buildSchema;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    public function getSchema(): Type
    {
        if (! $this->schema) {
            $this->schema = new Type();

            if ($this->buildSchema) {
                ($this->buildSchema)($this->schema);
            }
        }

        return $this->schema;
    }

    public function url($model, Context $context): string
    {
        $id = $this->adapter->getId($model);

        return $context->getApi()->getBasePath()."/$this->type/$id";
    }

    /**
     * Apply the resource type's scopes to a query.
     */
    public function applyScopes($query, Context $context): void
    {
        run_callbacks(
            $this->getSchema()->getListeners('scope'),
            [$query, $context]
        );
    }

    /**
     * Apply the resource type's filters to a query.
     */
    public function applySort($query, string $sortString, Context $context): void
    {
        $schema = $this->getSchema();
        $customSorts = $schema->getSorts();
        $fields = $schema->getFields();

        foreach ($this->parseSortString($sortString) as [$name, $direction]) {
            if (
                isset($customSorts[$name])
                && evaluate($customSorts[$name]->getVisible(), [$context])
            ) {
                $customSorts[$name]->getCallback()($query, $direction, $context);
                continue;
            }

            $field = $fields[$name] ?? null;

            if (
                $field instanceof Attribute
                && evaluate($field->getSortable(), [$context])
            ) {
                $this->adapter->sortByAttribute($query, $field, $direction);
                continue;
            }

            throw (new BadRequestException("Invalid sort field: $name"))->setSourceParameter('sort');
        }
    }

    private function parseSortString(string $string): array
    {
        return array_map(function ($field) {
            if ($field[0] === '-') {
                return [substr($field, 1), 'desc'];
            } else {
                return [$field, 'asc'];
            }
        }, explode(',', $string));
    }

    /**
     * Apply the resource type's filters to a query.
     */
    public function applyFilters($query, array $filters, Context $context): void
    {
        $schema = $this->getSchema();
        $customFilters = $schema->getFilters();
        $fields = $schema->getFields();

        foreach ($filters as $name => $value) {
            if ($name === 'id') {
                $this->adapter->filterByIds($query, explode(',', $value));
                continue;
            }

            if (
                isset($customFilters[$name])
                && evaluate($customFilters[$name]->getVisible(), [$context])
            ) {
                $customFilters[$name]->getCallback()($query, $value, $context);
                continue;
            }

            [$name, $sub] = explode('.', $name, 2) + [null, null];
            $field = $fields[$name] ?? null;

            if ($field && evaluate($field->getFilterable(), [$context])) {
                if ($field instanceof Attribute && $sub === null) {
                    $this->filterByAttribute($query, $field, $value);
                    continue;
                }

                if ($field instanceof Relationship) {
                    if (is_string($relatedType = $field->getType())) {
                        $relatedResource = $context->getApi()->getResourceType($relatedType);

                        $this->adapter->filterByRelationship($query, $field, function ($query) use ($relatedResource, $sub, $value, $context) {
                            $relatedResource->applyFilters($query, [($sub ?? 'id') => $value], $context);
                        });
                    }
                    continue;
                }
            }

            throw (new BadRequestException("Invalid filter: $name"))->setSourceParameter("filter[$name]");
        }
    }

    private function filterByAttribute($query, Attribute $attribute, $value): void
    {
        if (preg_match('/(.+)\.\.(.+)/', $value, $matches)) {
            if ($matches[1] !== '*') {
                $this->adapter->filterByAttribute($query, $attribute, $value, '>=');
            }
            if ($matches[2] !== '*') {
                $this->adapter->filterByAttribute($query, $attribute, $value, '<=');
            }

            return;
        }

        foreach (['>=', '>', '<=', '<'] as $operator) {
            if (strpos($value, $operator) === 0) {
                $this->adapter->filterByAttribute($query, $attribute, substr($value, strlen($operator)), $operator);

                return;
            }
        }

        $this->adapter->filterByAttribute($query, $attribute, $value);
    }
}
