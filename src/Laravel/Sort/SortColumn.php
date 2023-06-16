<?php

namespace Tobyz\JsonApiServer\Laravel\Sort;

use Tobyz\JsonApiServer\Schema\Concerns\HasProperty;
use Tobyz\JsonApiServer\Schema\Sort;

class SortColumn extends Sort
{
    use HasProperty;

    public function apply(object $query, string $direction): void
    {
        $query->orderBy($this->property ?: $this->name, $direction);
    }
}
