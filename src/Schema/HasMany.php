<?php

namespace Tobscure\JsonApiServer\Schema;

class HasMany extends Relationship
{
    public $includable = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->resource = $name;
    }

    public function includable()
    {
        $this->includable = true;

        return $this;
    }

    public function included()
    {
        $this->includable();

        return parent::included();
    }
}
