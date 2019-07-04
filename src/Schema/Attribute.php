<?php

namespace Tobscure\JsonApiServer\Schema;

use Closure;

class Attribute extends Field
{
    public $location = 'attributes';
    public $sortable = false;
    public $sorter;

    public function sortable(Closure $callback = null)
    {
        $this->sortable = true;
        $this->sorter = $callback;

        return $this;
    }
}
