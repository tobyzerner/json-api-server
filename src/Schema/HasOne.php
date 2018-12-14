<?php

namespace Tobscure\JsonApiServer\Schema;

class HasOne extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->resource = str_plural($name);
    }
}
