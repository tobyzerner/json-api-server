<?php

namespace Tobyz\JsonApiServer\Schema;

final class HasMany extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type($name);
    }
}
