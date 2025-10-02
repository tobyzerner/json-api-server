<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Pagination\Pagination;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Listable;

use function Tobyz\JsonApiServer\apply_filters;
use function Tobyz\JsonApiServer\parse_sort_string;

trait ListsResources
{
    private function listResources(
        object $query,
        Collection&Listable $collection,
        Context $context,
        ?string $defaultSort = null,
        ?Pagination $pagination = null,
    ): array {
        $context = $context->withCollection($collection)->withQuery($query);

        $this->applyListSorts(
            $query,
            $collection,
            $context,
            $defaultSort ?? $collection->defaultSort(),
        );

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
        ?string $defaultSort,
    ): void {
        if (!($sortString = $context->queryParam('sort', $defaultSort))) {
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

            throw (new BadRequestException("Invalid sort: $name"))->setSource([
                'parameter' => 'sort',
            ]);
        }
    }

    private function applyListFilters(
        object $query,
        Collection&Listable $collection,
        Context $context,
    ): void {
        if (!($filters = $context->queryParam('filter'))) {
            return;
        }

        if (!is_array($filters)) {
            throw (new BadRequestException('filter must be an array'))->setSource([
                'parameter' => 'filter',
            ]);
        }

        try {
            apply_filters($query, $filters, $collection, $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['parameter' => 'filter']);
        }
    }
}
