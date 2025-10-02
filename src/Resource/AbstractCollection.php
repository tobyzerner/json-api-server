<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Pagination\Pagination;

abstract class AbstractCollection implements Collection
{
    public function endpoints(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [];
    }

    public function sorts(): array
    {
        return [];
    }

    public function defaultSort(): ?string
    {
        return null;
    }

    public function pagination(): ?Pagination
    {
        return null;
    }
}
