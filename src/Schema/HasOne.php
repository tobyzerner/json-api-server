<?php

namespace Tobyz\JsonApiServer\Schema;

use Doctrine\Common\Inflector\Inflector;

final class HasOne extends Relationship
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(Inflector::pluralize($name));
    }
}
