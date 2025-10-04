<?php

namespace Tobyz\JsonApiServer\Pagination;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Pagination\InvalidPageCursorException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Pagination\Concerns\HasSizeParameter;
use Tobyz\JsonApiServer\Resource\CursorPaginatable;

class CursorPagination implements Pagination
{
    use HasSizeParameter;

    public const PROFILE_URI = 'https://jsonapi.org/profiles/ethanresnick/cursor-pagination';

    public readonly int $size;
    public readonly ?string $after;
    public readonly ?string $before;

    public function __construct(int $defaultSize = 20, ?int $maxSize = 50)
    {
        $this->configureSizeParameter($defaultSize, $maxSize);
    }

    public function paginate(object $query, Context $context): array
    {
        $context->activateProfile(self::PROFILE_URI);

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

    private function getCursor(Context $context, string $key): ?string
    {
        $cursor = $context->queryParam('page')[$key] ?? null;

        if ($cursor && !is_string($cursor)) {
            throw (new InvalidPageCursorException())->source(['parameter' => "page[$key]"]);
        }

        return $cursor;
    }
}
