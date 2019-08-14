<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;

abstract class Field
{
    public $name;
    public $property;
    public $isVisible;
    public $isWritable;
    public $getter;
    public $setter;
    public $saver;
    public $savedCallbacks = [];
    public $default;
    public $validators = [];
    public $filterable = false;
    public $filter;

    public function __construct(string $name)
    {
        $this->name = $name;

        $this->visible();
        $this->readonly();
    }

    public function property(string $property)
    {
        $this->property = $property;

        return $this;
    }

    public function visibleIf(Closure $condition)
    {
        $this->isVisible = $condition;

        return $this;
    }

    public function visible()
    {
        return $this->visibleIf(function () {
            return true;
        });
    }

    public function hiddenIf(Closure $condition)
    {
        return $this->visibleIf(function (...$args) use ($condition) {
            return ! $condition(...$args);
        });
    }

    public function hidden()
    {
        return $this->hiddenIf(function () {
            return true;
        });
    }

    public function writableIf(Closure $condition)
    {
        $this->isWritable = $condition;

        return $this;
    }

    public function writable()
    {
        return $this->writableIf(function () {
            return true;
        });
    }

    public function readonlyIf(Closure $condition)
    {
        return $this->writableIf(function (...$args) use ($condition) {
            return ! $condition(...$args);
        });
    }

    public function readonly()
    {
        return $this->readonlyIf(function () {
            return true;
        });
    }

    public function get($callback)
    {
        $this->getter = $this->wrap($callback);

        return $this;
    }

    public function set(Closure $callback)
    {
        $this->setter = $callback;

        return $this;
    }

    public function save(Closure $callback)
    {
        $this->saver = $callback;

        return $this;
    }

    public function saved(Closure $callback)
    {
        $this->savedCallbacks[] = $callback;

        return $this;
    }

    public function default($value)
    {
        $this->default = $this->wrap($value);

        return $this;
    }

    public function validate(Closure $callback)
    {
        $this->validators[] = $callback;

        return $this;
    }

    public function filterable(Closure $callback = null)
    {
        $this->filterable = true;
        $this->filter = $callback;

        return $this;
    }

    protected function wrap($value)
    {
        if (! $value instanceof Closure) {
            $value = function () use ($value) {
                return $value;
            };
        }

        return $value;
    }
}
