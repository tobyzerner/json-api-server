<?php

namespace Tobyz\JsonApiServer\Pagination;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Resource\Paginatable;

class OffsetPagination implements Pagination
{
    public int $offset;
    public int $limit;

    public function __construct(
        public Context $context,
        public int $defaultLimit = 20,
        public int $maxLimit = 50,
    ) {
        $this->offset = $this->getOffset($context);
        $this->limit = $this->getLimit($context);
    }

    public function apply($query): void
    {
        $collection = $this->context->collection;

        if (!$collection instanceof Paginatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Paginatable::class),
            );
        }

        $collection->paginate($query, $this);
    }

    public function meta(): array
    {
        return [
            'offset' => $this->offset,
            'limit' => $this->limit,
        ];
    }

    public function links(int $count, ?int $total): array
    {
        $links = [];

        if ($this->offset > 0) {
            $links['first'] = $this->buildUrl(['page' => ['offset' => 0]]);

            $prevOffset = $this->offset - $this->limit;

            if ($prevOffset < 0) {
                $params = ['page' => ['offset' => 0, 'limit' => $this->offset]];
            } else {
                $params = ['page' => ['offset' => max(0, $prevOffset)]];
            }

            $links['prev'] = $this->buildUrl($params);
        }

        if ($total !== null && $this->limit && $this->offset + $this->limit < $total) {
            $links['last'] = $this->buildUrl([
                'page' => ['offset' => floor(($total - 1) / $this->limit) * $this->limit],
            ]);
        }

        if (($total === null && $count === $this->limit) || $this->offset + $count < $total) {
            $links['next'] = $this->buildUrl([
                'page' => ['offset' => $this->offset + $this->limit],
            ]);
        }

        return $links;
    }

    private function buildUrl(array $overrideParams = []): string
    {
        [$selfUrl] = explode('?', $this->context->request->getUri(), 2);

        $queryParams = array_replace_recursive(
            $this->context->request->getQueryParams(),
            $overrideParams,
        );

        if (isset($queryParams['page']['offset']) && $queryParams['page']['offset'] <= 0) {
            unset($queryParams['page']['offset']);
        }

        if (isset($queryParams['filter'])) {
            foreach ($queryParams['filter'] as $k => &$v) {
                $v = $v === null ? '' : $v;
            }
        }

        ksort($queryParams);

        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        return $selfUrl . ($queryString ? '?' . $queryString : '');
    }

    private function getOffset(Context $context): int
    {
        if ($offset = $context->queryParam('page')['offset'] ?? null) {
            if (preg_match('/\D+/', $offset) || $offset < 0) {
                throw (new BadRequestException(
                    'page[offset] must be a non-negative integer',
                ))->setSource([
                    'parameter' => 'page[offset]',
                ]);
            }

            return $offset;
        }

        return 0;
    }

    private function getLimit(Context $context): int
    {
        if ($limit = $context->queryParam('page')['limit'] ?? null) {
            if (preg_match('/\D+/', $limit) || $limit < 1) {
                throw (new BadRequestException(
                    'page[limit] must be a positive integer',
                ))->setSource(['parameter' => 'page[limit]']);
            }

            if ($this->maxLimit) {
                $limit = min($this->maxLimit, $limit);
            }

            return $limit;
        }

        return $this->defaultLimit;
    }
}
