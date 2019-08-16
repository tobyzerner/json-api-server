<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;

final class Attribute extends Field
{
    private $sortable = false;

    public function sortable(Closure $callback = null)
    {
        $this->sortable = $callback ?: true;

        return $this;
    }

    public function notSortable()
    {
        $this->sortable = false;

        return $this;
    }

    public function getSortable()
    {
        return $this->sortable;
    }

    public function getLocation(): string
    {
        return 'attributes';
    }
}
