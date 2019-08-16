<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use function Tobyz\JsonApiServer\negate;
use Tobyz\JsonApiServer\Schema\Concerns\HasListeners;

final class Type
{
    use HasListeners;

    private $fields = [];
    private $meta = [];
    private $paginate = 20;
    private $limit = 50;
    private $countable = true;
    private $defaultSort;
    private $scopes = [];
    private $saver;
    private $creatable = false;
    private $create;
    private $updatable = false;
    private $deletable = false;
    private $delete;

    public function attribute(string $name): Attribute
    {
        return $this->field(Attribute::class, $name);
    }

    public function hasOne(string $name): HasOne
    {
        return $this->field(HasOne::class, $name);
    }

    public function hasMany(string $name): HasMany
    {
        return $this->field(HasMany::class, $name);
    }

    private function field(string $class, string $name)
    {
        if (! isset($this->fields[$name]) || ! $this->fields[$name] instanceof $class) {
            $this->fields[$name] = new $class($name);
        }

        return $this->fields[$name];
    }

    public function removeField(string $name)
    {
        unset($this->fields[$name]);

        return $this;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function meta(string $name, $value)
    {
        return $this->meta[$name] = new Meta($name, $value);
    }

    public function removeMeta(string $name)
    {
        unset($this->meta[$name]);

        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function paginate(int $perPage)
    {
        $this->paginate = $perPage;
    }

    public function dontPaginate()
    {
        $this->paginate = null;
    }

    public function getPaginate(): int
    {
        return $this->paginate;
    }

    public function limit(int $limit)
    {
        $this->limit = $limit;
    }

    public function noLimit()
    {
        $this->limit = null;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function countable()
    {
        $this->countable = true;
    }

    public function uncountable()
    {
        $this->countable = false;
    }

    public function isCountable(): bool
    {
        return $this->countable;
    }

    public function scope(Closure $callback)
    {
        $this->scopes[] = $callback;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function create(?Closure $callback)
    {
        $this->create = $callback;
    }

    public function getCreator()
    {
        return $this->create;
    }

    public function creatable(Closure $condition = null)
    {
        $this->creatable = $condition ?: true;

        return $this;
    }

    public function notCreatable(Closure $condition = null)
    {
        $this->creatable = $condition ? negate($condition) : false;

        return $this;
    }

    public function getCreatable()
    {
        return $this->creatable;
    }

    public function creating(Closure $callback)
    {
        $this->listeners['creating'][] = $callback;

        return $this;
    }

    public function created(Closure $callback)
    {
        $this->listeners['created'][] = $callback;

        return $this;
    }

    public function updatable(Closure $condition = null)
    {
        $this->updatable = $condition ?: true;

        return $this;
    }

    public function notUpdatable(Closure $condition = null)
    {
        $this->updatable = $condition ? negate($condition) : false;

        return $this;
    }

    public function getUpdatable()
    {
        return $this->updatable;
    }

    public function updating(Closure $callback)
    {
        $this->listeners['updating'][] = $callback;

        return $this;
    }

    public function updated(Closure $callback)
    {
        $this->listeners['updated'][] = $callback;

        return $this;
    }

    public function save(?Closure $callback)
    {
        $this->saver = $callback;

        return $this;
    }

    public function getSaver()
    {
        return $this->saver;
    }

    public function deletable(Closure $condition = null)
    {
        $this->deletable = $condition ?: true;

        return $this;
    }

    public function notDeletable(Closure $condition = null)
    {
        $this->deletable = $condition ? negate($condition) : false;

        return $this;
    }

    public function getDeletable()
    {
        return $this->deletable;
    }

    public function delete(?Closure $callback)
    {
        $this->delete = $callback;

        return $this;
    }

    public function getDelete()
    {
        return $this->delete;
    }

    public function deleting(Closure $callback)
    {
        $this->listeners['deleting'][] = $callback;

        return $this;
    }

    public function deleted(Closure $callback)
    {
        $this->listeners['deleted'][] = $callback;

        return $this;
    }

    public function defaultSort(?string $sort)
    {
        $this->defaultSort = $sort;

        return $this;
    }

    public function getDefaultSort()
    {
        return $this->defaultSort;
    }
}
