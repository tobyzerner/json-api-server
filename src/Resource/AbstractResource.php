<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Field;

abstract class AbstractResource implements Resource, Collection
{
    public function name(): string
    {
        return $this->type();
    }

    public function resources(): array
    {
        return [$this->type()];
    }

    public function resource(object $model, Context $context): ?string
    {
        return $this->type();
    }

    public function endpoints(): array
    {
        return [];
    }

    public function resolveEndpoints(): array
    {
        return $this->endpoints();
    }

    public function fields(): array
    {
        return [];
    }

    public function resolveFields(): array
    {
        return $this->fields();
    }

    public function meta(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [];
    }

    public function resolveFilters(): array
    {
        return $this->filters();
    }

    public function sorts(): array
    {
        return [];
    }

    public function resolveSorts(): array
    {
        return $this->sorts();
    }

    public function getId(object $model, Context $context): string
    {
        return $model->id;
    }

    public function getValue(object $model, Field $field, Context $context): mixed
    {
        return $model->{$field->property ?: $field->name} ?? null;
    }
}
