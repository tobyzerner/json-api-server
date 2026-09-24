<?php

namespace Tobyz\JsonApiServer;

use LogicException;
use Tobyz\JsonApiServer\Exception\Filter\InvalidFilterStructureException;
use Tobyz\JsonApiServer\Exception\Filter\UnknownFilterException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\SupportsBooleanFilters;
use Tobyz\JsonApiServer\Schema\Filter;

class Filterer
{
    /** @var array<string, Filter> */
    private array $definitions = [];

    public function __construct(
        private readonly Collection&Listable $collection,
        private Context $context,
    ) {
        $this->context = $context->withCollection($collection);

        foreach ($collection->filters() as $filter) {
            if (isset($this->definitions[$filter->name])) {
                throw new LogicException(
                    "Filter '$filter->name' is defined more than once in collection '{$collection->name()}'.",
                );
            }

            $this->definitions[$filter->name] = $filter;
        }
    }

    public function apply($query, array $filters): void
    {
        $this->context = $this->context->withFilters($filters);

        // Normalize and validate the whole bag before visibility callbacks run.
        $this->context->filters();

        $this->applyGroup(
            $query,
            $filters,
            'and',
            [],
            array_filter($this->definitions, fn(Filter $filter) => $filter->isVisible($this->context)),
        );
    }

    public function normalize(array $filters): array
    {
        foreach ($filters as $key => $value) {
            try {
                if (
                    is_int($key)
                    || $this->collection instanceof SupportsBooleanFilters && in_array($key, ['and', 'or', 'not'])
                ) {
                    if (!is_array($value)) {
                        throw new InvalidFilterStructureException();
                    }

                    $filters[$key] = $this->normalize($value);
                } elseif (isset($this->definitions[$key])) {
                    $filters[$key] = $this->definitions[$key]->deserializeValue($value);
                }
            } catch (Sourceable $e) {
                throw $e->prependSourcePath($key);
            }
        }

        return $filters;
    }

    private function applyGroup($query, array $filters, string $boolean, array $path, array $availableFilters): void
    {
        $clauses = [];

        foreach ($filters as $key => $value) {
            $keyPath = [...$path, $key];

            if (
                is_int($key)
                || $this->collection instanceof SupportsBooleanFilters && in_array($key, ['and', 'or', 'not'])
            ) {
                $clauses[] = fn($query) => $this->applyGroup(
                    $query,
                    $value,
                    is_int($key) ? 'and' : $key,
                    $keyPath,
                    $availableFilters,
                );

                continue;
            }

            if (!($filter = $availableFilters[$key] ?? null)) {
                throw (new UnknownFilterException($key))->prependSourcePath(...$keyPath);
            }

            $clauses[] = function ($query) use ($filter, $value, $keyPath) {
                try {
                    $filter->apply($query, $value, $this->context);
                } catch (Sourceable $e) {
                    throw $e->prependSourcePath(...$keyPath);
                }
            };
        }

        if (!$clauses) {
            return;
        }

        if ($this->collection instanceof SupportsBooleanFilters) {
            if ($boolean === 'or') {
                $this->collection->filterOr($query, $clauses);

                return;
            }

            if ($boolean === 'not') {
                $this->collection->filterNot($query, $clauses);

                return;
            }
        }

        foreach ($clauses as $clause) {
            $clause($query);
        }
    }
}
