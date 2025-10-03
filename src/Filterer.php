<?php

namespace Tobyz\JsonApiServer;

use Tobyz\JsonApiServer\Exception\BadRequestException;
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
        $this->applyGroup($query, $filters, 'and', []);
    }

    private function applyGroup($query, array $filters, string $boolean, array $path): void
    {
        $clauses = [];
        $availableFilters = $this->resolveAvailableFilters();

        foreach ($filters as $key => $value) {
            $keyPath = [...$path, $key];

            if (
                $this->collection instanceof SupportsBooleanFilters &&
                in_array($key, ['and', 'or', 'not'])
            ) {
                if (!is_array($value)) {
                    throw $this->badRequest(
                        $this->context->translate('filter.structure_invalid'),
                        $keyPath,
                    );
                }

                $clauses[] = fn($query) => $this->applyGroup($query, $value, $key, $keyPath);

                continue;
            }

            if (is_int($key)) {
                if (!is_array($value)) {
                    throw $this->badRequest(
                        $this->context->translate('filter.structure_invalid'),
                        $keyPath,
                    );
                }

                $clauses[] = fn($query) => $this->applyGroup($query, $value, 'and', $keyPath);

                continue;
            }

            if (!($filter = $availableFilters[$key] ?? null)) {
                throw $this->badRequest(
                    $this->context->translate('filter.invalid', ['filter' => $key]),
                    $keyPath,
                );
            }

            $clauses[] = fn($query) => $filter->apply($query, $value, $this->context);
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

    private function badRequest(string $message, array $path): BadRequestException
    {
        return (new BadRequestException($message))->setSource([
            'parameter' =>
                '[' . implode('][', array_map(fn($segment) => (string) $segment, $path)) . ']',
        ]);
    }
}
