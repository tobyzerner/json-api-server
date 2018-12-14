<?php

namespace Tobscure\JsonApiServer\Schema;

use Doctrine\Common\Inflector\Inflector;

class HasOne extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->resource = Inflector::pluralize($name);
    }
}
