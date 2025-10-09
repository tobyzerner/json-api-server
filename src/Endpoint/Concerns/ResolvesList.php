<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\ProvidesParameters;
use Tobyz\JsonApiServer\Exception\Filter\InvalidFilterParameterException;
use Tobyz\JsonApiServer\Exception\Request\InvalidSortException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Pagination\Pagination;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;

use function Tobyz\JsonApiServer\apply_filters;
use function Tobyz\JsonApiServer\parse_sort_string;

trait ResolvesList
{
    protected function listParameters(
        Collection $collection,
        ?string $defaultSort = null,
        ?Pagination $pagination = null,
    ): array {
        $params = [];

        if ($filters = $collection->filters()) {
            $params[] = Parameter::make('filter')->type(Type\Obj::make());

            // TODO: properties of above?
            foreach ($filters as $filter) {
                $params[] = Parameter::make("filter[{$filter->name}]")->type(Type\Any::make());
            }
        }

        if ($sorts = $collection->sorts()) {
            $params[] = Parameter::make('sort')
                ->type(Type\Str::make())
                ->default($defaultSort ?? $collection->defaultSort());
        }

        $pagination ??= $collection->pagination();

        if ($pagination instanceof ProvidesParameters) {
            $params = array_merge($params, $pagination->parameters());
        }

        return $params;
    }

    private function resolveList(
        object $query,
        Collection&Listable $collection,
        Context $context,
        ?Pagination $pagination = null,
    ): array {
        $context = $context->withCollection($collection)->withQuery($query);

        $this->applyListSorts($query, $collection, $context);

        $this->applyListFilters($query, $collection, $context);

        if (
            $collection instanceof Countable &&
            !is_null($total = $collection->count($query, $context))
        ) {
            $context->documentMeta['page']['total'] = $total;
        }

        if ($pagination ??= $collection->pagination()) {
            return $pagination->paginate($query, $context);
        }

        return $collection->results($query, $context);
    }

    private function applyListSorts(
        object $query,
        Collection&Listable $collection,
        Context $context,
    ): void {
        if (!($sortString = $context->parameter('sort'))) {
            return;
        }

        $sorts = $collection->sorts();

        foreach (parse_sort_string($sortString) as [$name, $direction]) {
            foreach ($sorts as $sort) {
                if ($sort->name === $name && $sort->isVisible($context)) {
                    $sort->apply($query, $direction, $context);
                    continue 2;
                }
            }

            throw (new InvalidSortException($name))->source(['parameter' => 'sort']);
        }
    }

    private function applyListFilters(
        object $query,
        Collection&Listable $collection,
        Context $context,
    ): void {
        if (!($filters = $context->parameter('filter'))) {
            return;
        }

        if (!is_array($filters)) {
            throw (new InvalidFilterParameterException())->source(['parameter' => 'filter']);
        }

        try {
            apply_filters($query, $filters, $collection, $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['parameter' => 'filter']);
        }
    }
}
