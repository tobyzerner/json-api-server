<?php

namespace Tobscure\JsonApiServer\Schema;

use Closure;
use Spatie\Macroable\Macroable;

abstract class Relationship extends Field
{
    use Macroable;

    public $location = 'relationships';
    public $included = false;
    public $resource;

    public function resource($resource)
    {
        $this->resource = $resource;

        return $this;
    }

    public function included()
    {
        $this->included = true;

        return $this;
    }
}
