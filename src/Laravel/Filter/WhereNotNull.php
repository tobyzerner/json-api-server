<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

class WhereNotNull extends Where
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->operators(['notnull']);
    }
}
