<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

class WhereNotNull extends Where
{
    public const SUPPORTED_OPERATORS = ['notnull'];
}
