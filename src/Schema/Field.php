<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use function Tobyz\JsonApiServer\negate;
use Tobyz\JsonApiServer\Schema\Concerns\HasListeners;
use function Tobyz\JsonApiServer\wrap;

abstract class Field
{
    use HasListeners;

    private $name;
    private $property;
    private $visible = true;
    private $writable = false;
    private $getter;
    private $setter;
    private $saver;
    private $default;
    private $filterable = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    abstract public function getLocation(): string;

    public function property(string $property)
    {
        $this->property = $property;

        return $this;
    }

    public function visible(Closure $condition = null)
    {
        $this->visible = $condition ?: true;

        return $this;
    }

    public function hidden(Closure $condition = null)
    {
        $this->visible = $condition ? negate($condition) : false;

        return $this;
    }

    public function writable(Closure $condition = null)
    {
        $this->writable = $condition ?: true;

        return $this;
    }

    public function readonly(Closure $condition = null)
    {
        $this->writable = $condition ? negate($condition) : false;

        return $this;
    }

    public function get($value)
    {
        $this->getter = wrap($value);

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
        $this->listeners['saved'][] = $callback;

        return $this;
    }

    public function default($value)
    {
        $this->default = wrap($value);

        return $this;
    }

    public function validate(Closure $callback)
    {
        $this->listeners['validate'][] = $callback;

        return $this;
    }

    public function filterable(Closure $callback = null)
    {
        $this->filterable = $callback ?: true;

        return $this;
    }

    public function notFilterable()
    {
        $this->filterable = false;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return bool|Closure
     */
    public function getVisible()
    {
        return $this->visible;
    }

    public function getWritable()
    {
        return $this->writable;
    }

    public function getGetter()
    {
        return $this->getter;
    }

    public function getSetter()
    {
        return $this->setter;
    }

    public function getSaver()
    {
        return $this->saver;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function getFilterable()
    {
        return $this->filterable;
    }
}
