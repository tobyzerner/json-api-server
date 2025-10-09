<?php

namespace Tobyz\JsonApiServer\Pagination;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\ProvidesDocumentLinks;
use Tobyz\JsonApiServer\Endpoint\ProvidesDocumentMeta;
use Tobyz\JsonApiServer\Endpoint\ProvidesParameters;
use Tobyz\JsonApiServer\Resource\Paginatable;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Meta;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;

class OffsetPagination implements
    Pagination,
    ProvidesParameters,
    ProvidesDocumentMeta,
    ProvidesDocumentLinks
{
    public function __construct(public int $defaultLimit = 20, public ?int $maxLimit = 50)
    {
    }

    public function parameters(): array
    {
        return [
            Parameter::make('page[offset]')
                ->type(Type\Integer::make()->minimum(0))
                ->default(fn() => 0),

            Parameter::make('page[limit]')
                ->type(
                    Type\Integer::make()
                        ->minimum(1)
                        ->maximum($this->maxLimit),
                )
                ->default(fn() => $this->defaultLimit),
        ];
    }

    public function paginate(object $query, Context $context): array
    {
        $collection = $context->collection;

        if (!$collection instanceof Paginatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Paginatable::class),
            );
        }

        $offset = $context->parameter('page[offset]');
        $limit = $context->parameter('page[limit]');

        $page = $collection->paginate($query, $offset, $limit, $context);

        if ($page->isFirstPage !== true && $offset > 0) {
            $context->documentLinks['first'] = $context->currentUrl(['page' => ['offset' => null]]);

            $prevOffset = $offset - $limit;

            if ($prevOffset < 0) {
                $params = ['page' => ['offset' => null, 'limit' => $offset]];
            } else {
                $params = ['page' => ['offset' => max(0, $prevOffset) ?: null]];
            }

            $context->documentLinks['prev'] = $context->currentUrl($params);
        }

        $total = $context->documentMeta['page']['total'] ?? null;

        if ($total !== null && $limit && $offset + $limit < $total) {
            $context->documentLinks['last'] = $context->currentUrl([
                'page' => ['offset' => floor(($total - 1) / $limit) * $limit ?: null],
            ]);
        }

        if (!$page->isLastPage) {
            $context->documentLinks['next'] = $context->currentUrl([
                'page' => ['offset' => $offset + $limit],
            ]);
        }

        return $page->results;
    }

    public function documentMeta(): array
    {
        return [
            Meta::make('page')->type(Type\Obj::make()->property('total', Type\Integer::make())),
        ];
    }

    public function documentLinks(): array
    {
        return [Link::make('first'), Link::make('prev'), Link::make('next'), Link::make('last')];
    }
}
