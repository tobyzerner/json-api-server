<?php

namespace Tobscure\JsonApiServer\Schema;

use Closure;
use Illuminate\Support\Traits\Macroable;

class Attribute extends Field
{
    use Macroable;

    public $location = 'attributes';
    public $sortable = false;
    public $sorter;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->property = snake_case($name);
    }

    public function sortable(Closure $callback = null)
    {
        $this->sortable = true;
        $this->sorter = $callback;

        return $this;
    }
}
