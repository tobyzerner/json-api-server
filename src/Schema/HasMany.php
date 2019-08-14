<?php

namespace Tobyz\JsonApiServer\Schema;

class HasMany extends Relationship
{
    public $includable = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->resource = $name;
    }
}
