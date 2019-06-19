<?php

namespace Tobscure\JsonApiServer\Schema;

use Closure;
use Spatie\Macroable\Macroable;

class Attribute extends Field
{
    use Macroable;

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
