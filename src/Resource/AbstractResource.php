<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Id;

abstract class AbstractResource extends AbstractCollection implements Resource
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

    public function fields(): array
    {
        return [];
    }

    public function meta(): array
    {
        return [];
    }

    public function id(): Id
    {
        return Id::make();
    }

    public function getValue(object $model, Field $field, Context $context): mixed
    {
        return $model->{$field->property ?: $field->name} ?? null;
    }
}
