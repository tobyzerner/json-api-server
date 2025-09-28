<?php

namespace Tobyz\JsonApiServer\Pagination;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Pagination\Concerns\BuildsUrls;
use Tobyz\JsonApiServer\Pagination\Concerns\HasSizeParameter;
use Tobyz\JsonApiServer\Resource\CursorPaginatable;

class CursorPagination implements Pagination
{
    use BuildsUrls;
    use HasSizeParameter;

    public readonly int $size;
    public readonly ?string $after;
    public readonly ?string $before;

    public function __construct(int $defaultSize = 20, ?int $maxSize = 50)
    {
        $this->configureSizeParameter($defaultSize, $maxSize);
    }

    public function paginate(object $query, Context $context): array
    {
        $size = $this->getSize($context, 'size');
        $after = $this->getCursor($context, 'after');
        $before = $this->getCursor($context, 'before');

        $collection = $context->collection;

        if (!$collection instanceof CursorPaginatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), CursorPaginatable::class),
            );
        }

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
            $context->documentLinks['prev'] = $this->buildUrl(
                [
                    'page' => [
                        'after' => null,
                        'before' => $collection->itemCursor($page->results[0], $query, $context),
                    ],
                ],
                $context,
            );
        }

        if ($page->results && !$page->isLastPage) {
            $context->documentLinks['next'] = $this->buildUrl(
                [
                    'page' => [
                        'before' => null,
                        'after' => $collection->itemCursor(end($page->results), $query, $context),
                    ],
                ],
                $context,
            );
        }

        return $page->results;
    }

    private function getCursor(Context $context, string $key): ?string
    {
        $cursor = $context->queryParam('page')[$key] ?? null;

        if ($cursor && !is_string($cursor)) {
            throw (new BadRequestException("page[$key] must be a cursor string"))->setSource([
                'parameter' => "page[$key]",
            ]);
        }

        return $cursor;
    }
}
