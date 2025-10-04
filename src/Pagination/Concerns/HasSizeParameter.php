<?php

namespace Tobyz\JsonApiServer\Pagination\Concerns;

use InvalidArgumentException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Pagination\InvalidPageSizeException;
use Tobyz\JsonApiServer\Exception\Pagination\MaxPageSizeExceededException;

trait HasSizeParameter
{
    private int $defaultSize;
    private ?int $maxSize;

    private function configureSizeParameter(int $defaultSize, ?int $maxSize): void
    {
        if ($defaultSize < 1) {
            throw new InvalidArgumentException('Default page size must be at least 1');
        }

        if ($maxSize !== null) {
            if ($maxSize < 1) {
                throw new InvalidArgumentException('Max page size must be at least 1');
            }

            $defaultSize = min($defaultSize, $maxSize);
        }

        $this->defaultSize = $defaultSize;
        $this->maxSize = $maxSize;
    }

    private function getSize(Context $context, string $parameter): int
    {
        $size = $context->queryParam('page')[$parameter] ?? null;

        if ($size !== null) {
            if (preg_match('/\D+/', $size) || $size < 1) {
                throw (new InvalidPageSizeException())->source(['parameter' => "page[$parameter]"]);
            }

            if ($this->maxSize && $size > $this->maxSize) {
                throw (new MaxPageSizeExceededException($this->maxSize))->source([
                    'parameter' => "page[$parameter]",
                ]);
            }

            return $size;
        }

        return $this->defaultSize;
    }
}
