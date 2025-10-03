<?php

namespace Tobyz\JsonApiServer\Pagination;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Pagination\Concerns\HasSizeParameter;
use Tobyz\JsonApiServer\Resource\Paginatable;

class OffsetPagination implements Pagination
{
    use HasSizeParameter;

    public function __construct(int $defaultLimit = 20, ?int $maxLimit = 50)
    {
        $this->configureSizeParameter($defaultLimit, $maxLimit);
    }

    public function paginate(object $query, Context $context): array
    {
        $offset = $this->getOffset($context);
        $limit = $this->getSize($context, 'limit');

        $collection = $context->collection;

        if (!$collection instanceof Paginatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Paginatable::class),
            );
        }

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

    private function getOffset(Context $context): int
    {
        if ($offset = $context->queryParam('page')['offset'] ?? null) {
            if (preg_match('/\D+/', $offset) || $offset < 0) {
                throw (new BadRequestException(
                    $context->translate('pagination.offset_invalid'),
                ))->setSource(['parameter' => 'page[offset]']);
            }

            return $offset;
        }

        return 0;
    }
}
