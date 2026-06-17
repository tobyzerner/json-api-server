<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Filter;
use Tobyz\JsonApiServer\Schema\Type;

class WhereExists extends Filter
{
    use UsesRelationship;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(Type\Boolean::make());
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    protected function applyValue(object $query, mixed $value, Context $context): void
    {
        $method = $value ? 'whereHas' : 'whereDoesntHave';

        $query->{$method}(
            $this->relationship ?: $this->name,
            $this->scope ? fn($query) => ($this->scope)($query, $context) : null,
        );
    }
}
