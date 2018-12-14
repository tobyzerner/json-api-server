<?php

namespace Tobscure\JsonApiServer\Schema;

use Closure;

class Builder
{
    public $fields = [];
    public $paginate = 20;
    public $scopes = [];
    public $isVisible;
    public $isCreatable;
    public $isDeletable;

    public function __construct()
    {
        $this->notCreatable();
        $this->notDeletable();
    }

    public function attribute(string $name, string $property = null): Attribute
    {
        return $this->field(Attribute::class, $name, $property);
    }

    public function hasOne(string $name, string $resource = null, string $property = null): HasOne
    {
        $field = $this->field(HasOne::class, $name, $property);

        if ($resource) {
            $field->resource($resource);
        }

        return $field;
    }

    public function hasMany(string $name, string $resource = null, string $property = null): HasMany
    {
        $field = $this->field(HasMany::class, $name, $property);

        if ($resource) {
            $field->resource($resource);
        }

        return $field;
    }

    public function paginate(?int $perPage)
    {
        $this->paginate = $perPage;
    }

    public function scope(Closure $callback)
    {
        $this->scopes[] = $callback;
    }

    public function creatableIf(Closure $condition)
    {
        $this->isCreatable = $condition;

        return $this;
    }

    public function creatable()
    {
        return $this->creatableIf(function () {
            return true;
        });
    }

    public function notCreatableIf(Closure $condition)
    {
        return $this->creatableIf(function (...$args) use ($condition) {
            return ! $condition(...$args);
        });
    }

    public function notCreatable()
    {
        return $this->notCreatableIf(function () {
            return true;
        });
    }

    public function deletableIf(Closure $condition)
    {
        $this->isDeletable = $condition;

        return $this;
    }

    public function deletable()
    {
        return $this->deletableIf(function () {
            return true;
        });
    }

    public function notDeletableIf(Closure $condition)
    {
        return $this->deletableIf(function (...$args) use ($condition) {
            return ! $condition(...$args);
        });
    }

    public function notDeletable()
    {
        return $this->notDeletableIf(function () {
            return true;
        });
    }

    private function field(string $class, string $name, string $property = null)
    {
        if (! isset($this->fields[$name]) || ! $this->fields[$name] instanceof $class) {
            $this->fields[$name] = new $class($name);
        }

        if ($property) {
            $this->fields[$name]->property($property);
        }

        return $this->fields[$name];
    }
}
