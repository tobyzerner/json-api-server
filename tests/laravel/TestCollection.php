<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\EloquentCollection;
use Tobyz\JsonApiServer\Laravel\UnionBuilder;

class TestCollection extends EloquentCollection
{
    public function __construct(
        private readonly string $name,
        private readonly array $resources,
        private readonly array $endpoints = [],
        private readonly array $sorts = [],
        private readonly ?Closure $scope = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function resources(): array
    {
        return $this->resources;
    }

    public function endpoints(): array
    {
        return $this->endpoints;
    }

    public function sorts(): array
    {
        return $this->sorts;
    }

    public function scope(UnionBuilder $query, Context $context): void
    {
        if ($this->scope) {
            ($this->scope)($query, $context);
        }
    }
}
