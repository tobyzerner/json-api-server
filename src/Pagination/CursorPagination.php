<?php

namespace Tobyz\JsonApiServer\Pagination;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\ProvidesDocumentLinks;
use Tobyz\JsonApiServer\Endpoint\ProvidesDocumentMeta;
use Tobyz\JsonApiServer\Endpoint\ProvidesParameters;
use Tobyz\JsonApiServer\Endpoint\ProvidesResourceMeta;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Resource\CursorPaginatable;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Meta;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;

class CursorPagination implements
    Pagination,
    ProvidesParameters,
    ProvidesDocumentMeta,
    ProvidesDocumentLinks,
    ProvidesResourceMeta
{
    public const PROFILE_URI = 'https://jsonapi.org/profiles/ethanresnick/cursor-pagination';

    public function __construct(public int $defaultSize = 20, public ?int $maxSize = 50)
    {
    }

    public function parameters(): array
    {
        return [
            Parameter::make('page[size]')
                ->type(
                    Type\Integer::make()
                        ->minimum(1)
                        ->maximum($this->maxSize),
                )
                ->default(fn() => $this->defaultSize),

            Parameter::make('page[after]')->type(Type\Str::make()),

            Parameter::make('page[before]')->type(Type\Str::make()),
        ];
    }

    public function paginate(object $query, Context $context): array
    {
        $context->activateProfile(self::PROFILE_URI);

        $collection = $context->collection;

        if (!$collection instanceof CursorPaginatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), CursorPaginatable::class),
            );
        }

        $size = $context->parameter('page[size]');
        $after = $context->parameter('page[after]');
        $before = $context->parameter('page[before]');

        try {
            $page = $collection->cursorPaginate($query, $size, $after, $before, $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['parameter' => 'page']);
        }

        foreach ($page->results as $model) {
            $context->resourceMeta($model, [
                'page' => ['cursor' => $collection->itemCursor($model, $query, $context)],
            ]);
        }

        if ($page->results && !$page->isFirstPage) {
            $context->documentLinks['prev'] = $context->currentUrl([
                'page' => [
                    'after' => null,
                    'before' => $collection->itemCursor($page->results[0], $query, $context),
                ],
            ]);
        }

        if ($page->results && !$page->isLastPage) {
            $context->documentLinks['next'] = $context->currentUrl([
                'page' => [
                    'before' => null,
                    'after' => $collection->itemCursor(end($page->results), $query, $context),
                ],
            ]);
        }

        if ($page->rangeTruncated !== null) {
            $context->documentMeta['page']['rangeTruncated'] = $page->rangeTruncated;
        }

        return $page->results;
    }

    public function documentMeta(): array
    {
        return [
            Meta::make('page')->type(
                Type\Obj::make()->property('rangeTruncated', Type\Boolean::make()),
            ),
        ];
    }

    public function documentLinks(): array
    {
        return [Link::make('prev'), Link::make('next')];
    }

    public function resourceMeta(): array
    {
        return [Meta::make('page')->type(Type\Obj::make()->property('cursor', Type\Str::make()))];
    }
}
