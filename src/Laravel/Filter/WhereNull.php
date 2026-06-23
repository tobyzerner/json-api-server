<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

class WhereNull extends Where
{
    public const SUPPORTED_OPERATORS = ['null'];
}
