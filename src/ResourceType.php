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
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
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
            $this->schema = new Type;

            if ($this->buildSchema) {
                ($this->buildSchema)($this->schema);
            }
        }

        return $this->schema;
    }

    public function scope($query, Context $context)
    {
        run_callbacks($this->getSchema()->getListeners('scope'), [$query, $context]);
    }

    public function filter($query, $filter, Context $context)
    {
        if (! is_array($filter)) {
            throw new BadRequestException('filter must be an array', 'filter');
        }

        $schema = $this->getSchema();
        $adapter = $this->getAdapter();
        $filters = $schema->getFilters();
        $fields = $schema->getFields();

        foreach ($filter as $name => $value) {
            if ($name === 'id') {
                $adapter->filterByIds($query, explode(',', $value));
                continue;
            }

            if (isset($filters[$name]) && evaluate($filters[$name]->getVisible(), [$context])) {
                $filters[$name]->getCallback()($query, $value, $context);
                continue;
            }

            if (isset($fields[$name]) && evaluate($fields[$name]->getFilterable(), [$context])) {
                if ($fields[$name] instanceof Attribute) {
                    $this->filterByAttribute($adapter, $query, $fields[$name], $value);
                } elseif ($fields[$name] instanceof HasOne) {
                    $value = array_filter(explode(',', $value));
                    $adapter->filterByHasOne($query, $fields[$name], $value);
                } elseif ($fields[$name] instanceof HasMany) {
                    $value = array_filter(explode(',', $value));
                    $adapter->filterByHasMany($query, $fields[$name], $value);
                }
                continue;
            }

            throw new BadRequestException("Invalid filter [$name]", "filter[$name]");
        }
    }

    private function filterByAttribute(AdapterInterface $adapter, $query, Attribute $attribute, $value)
    {
        if (preg_match('/(.+)\.\.(.+)/', $value, $matches)) {
            if ($matches[1] !== '*') {
                $adapter->filterByAttribute($query, $attribute, $value, '>=');
            }
            if ($matches[2] !== '*') {
                $adapter->filterByAttribute($query, $attribute, $value, '<=');
            }

            return;
        }

        foreach (['>=', '>', '<=', '<'] as $operator) {
            if (strpos($value, $operator) === 0) {
                $adapter->filterByAttribute($query, $attribute, substr($value, strlen($operator)), $operator);

                return;
            }
        }

        $adapter->filterByAttribute($query, $attribute, $value);
    }
}
