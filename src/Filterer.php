<?php

namespace Tobyz\JsonApiServer;

use Tobyz\JsonApiServer\Exception\Filter\InvalidFilterStructureException;
use Tobyz\JsonApiServer\Exception\Filter\UnknownFilterException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\SupportsBooleanFilters;

class Filterer
{
    public function __construct(
        private readonly Collection&Listable $collection,
        private Context $context,
    ) {
        $this->context = $context->withCollection($collection);
    }

    public function apply($query, array $filters): void
    {
        $this->applyGroup($query, $filters, 'and', [], $this->resolveAvailableFilters());
    }

    private function applyGroup(
        $query,
        array $filters,
        string $boolean,
        array $path,
        array $availableFilters,
    ): void {
        $clauses = [];

        foreach ($filters as $key => $value) {
            $keyPath = [...$path, $key];

            if (
                $this->collection instanceof SupportsBooleanFilters &&
                in_array($key, ['and', 'or', 'not'])
            ) {
                if (!is_array($value)) {
                    throw $this->badRequest(new InvalidFilterStructureException(), $keyPath);
                }

                $clauses[] = fn($query) => $this->applyGroup(
                    $query,
                    $value,
                    $key,
                    $keyPath,
                    $availableFilters,
                );

                continue;
            }

            if (is_int($key)) {
                if (!is_array($value)) {
                    throw $this->badRequest(new InvalidFilterStructureException(), $keyPath);
                }

                $clauses[] = fn($query) => $this->applyGroup(
                    $query,
                    $value,
                    'and',
                    $keyPath,
                    $availableFilters,
                );

                continue;
            }

            if (!($filter = $availableFilters[$key] ?? null)) {
                throw $this->badRequest(new UnknownFilterException($key), $keyPath);
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

    private function resolveAvailableFilters(): array
    {
        $filters = [];

        foreach ($this->collection->filters() as $filter) {
            if ($filter->isVisible($this->context)) {
                $filters[$filter->name] = $filter;
            }
        }

        return $filters;
    }

    private function badRequest(
        InvalidFilterStructureException|UnknownFilterException $exception,
        array $path,
    ): InvalidFilterStructureException|UnknownFilterException {
        return $exception->prependSourcePath(...$path);
    }
}
