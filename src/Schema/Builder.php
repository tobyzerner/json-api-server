<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;

class Builder
{
    public $fields = [];
    public $meta = [];
    public $paginate = 20;
    public $limit = 50;
    public $countable = true;
    public $scopes = [];
    public $isCreatable;
    public $creatingCallbacks = [];
    public $createdCallbacks = [];
    public $isUpdatable;
    public $updatingCallbacks = [];
    public $updatedCallbacks = [];
    public $isDeletable;
    public $deletingCallbacks = [];
    public $deletedCallbacks = [];
    public $defaultSort;
    public $createModel;
    public $saver;

    public function __construct()
    {
        $this->visible();
        $this->notCreatable();
        $this->notUpdatable();
        $this->notDeletable();
    }

    public function attribute(string $name, string $property = null): Attribute
    {
        return $this->field(Attribute::class, $name, $property);
    }

    public function hasOne(string $name, $resource = null, string $property = null): HasOne
    {
        $field = $this->field(HasOne::class, $name, $property);

        if ($resource) {
            $field->resource($resource);
        }

        return $field;
    }

    public function morphOne(string $name, string $property = null): HasOne
    {
        return $this->field(HasOne::class, $name, $property)->resource(null);
    }

    public function hasMany(string $name, $resource = null, string $property = null): HasMany
    {
        $field = $this->field(HasMany::class, $name, $property);

        if ($resource) {
            $field->resource($resource);
        }

        return $field;
    }

    public function meta(string $name, $value)
    {
        return $this->meta[$name] = new Meta($name, $value);
    }

    public function paginate(?int $perPage)
    {
        $this->paginate = $perPage;
    }

    public function limit(?int $limit)
    {
        $this->limit = $limit;
    }

    public function countable()
    {
        $this->countable = true;
    }

    public function uncountable()
    {
        $this->countable = false;
    }

    public function createModel(Closure $callback)
    {
        $this->createModel = $callback;
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

    public function creating(Closure $callback)
    {
        $this->creatingCallbacks[] = $callback;
    }

    public function created(Closure $callback)
    {
        $this->createdCallbacks[] = $callback;
    }

    public function updatableIf(Closure $condition)
    {
        $this->isUpdatable = $condition;

        return $this;
    }

    public function updatable()
    {
        return $this->updatableIf(function () {
            return true;
        });
    }

    public function notUpdatableIf(Closure $condition)
    {
        return $this->updatableIf(function (...$args) use ($condition) {
            return ! $condition(...$args);
        });
    }

    public function notUpdatable()
    {
        return $this->notUpdatableIf(function () {
            return true;
        });
    }

    public function updating(Closure $callback)
    {
        $this->updatingCallbacks[] = $callback;
    }

    public function updated(Closure $callback)
    {
        $this->updatedCallbacks[] = $callback;
    }

    public function save(Closure $callback)
    {
        $this->saver = $callback;

        return $this;
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

    public function deleting(Closure $callback)
    {
        $this->deletingCallbacks[] = $callback;
    }

    public function deleted(Closure $callback)
    {
        $this->deletedCallbacks[] = $callback;
    }

    public function defaultSort(string $sort)
    {
        $this->defaultSort = $sort;
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
