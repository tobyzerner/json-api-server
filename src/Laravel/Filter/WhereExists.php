<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;

class WhereExists extends Filter
{
    use UsesRelationship;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function apply(object $query, array|string $value, Context $context): void
    {
        $method = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'whereHas' : 'whereDoesntHave';

        $query->{$method}(
            $this->relationship ?: $this->name,
            $this->scope ? fn($query) => ($this->scope)($query, $context) : null,
        );
    }
}
