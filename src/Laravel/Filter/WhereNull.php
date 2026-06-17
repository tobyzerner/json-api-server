<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

class WhereNull extends Where
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->operators(['null']);
    }
}
