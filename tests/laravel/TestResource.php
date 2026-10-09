<?php

namespace Tobyz\Tests\JsonApiServer\laravel;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Schema\Id;

/**
 * An Eloquent resource configured through its constructor, so tests can
 * declare the schema they need inline.
 */
class TestResource extends EloquentResource
{
    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $model
     */
    public function __construct(
        private readonly string $type,
        private readonly string $model,
        private readonly array $endpoints = [],
        private readonly array $fields = [],
        private readonly array $filters = [],
        private readonly array $sorts = [],
        private readonly ?string $defaultSort = null,
        private readonly ?Closure $scope = null,
        private readonly ?Closure $listScope = null,
        private readonly array $meta = [],
        private readonly ?Id $id = null,
    ) {}

    public function id(): Id
    {
        return $this->id ?? parent::id();
    }

    public function type(): string
    {
        return $this->type;
    }

    public function newModel(Context $context): object
    {
        return new $this->model();
    }

    public function endpoints(): array
    {
        return $this->endpoints;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function sorts(): array
    {
        return $this->sorts;
    }

    public function defaultSort(): ?string
    {
        return $this->defaultSort;
    }

    public function scope(Builder $query, Context $context): void
    {
        if ($this->scope) {
            ($this->scope)($query, $context);
        }
    }

    public function scopeList(Builder $query, Context $context): void
    {
        if ($this->listScope) {
            ($this->listScope)($query, $context);
        }
    }
}
