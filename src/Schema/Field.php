<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Schema\Concerns\HasListeners;
use function Tobyz\JsonApiServer\negate;
use function Tobyz\JsonApiServer\wrap;

abstract class Field
{
    use HasListeners;

    private $name;
    private $description;
    private $property;
    private $visible = true;
    private $single = false;
    private $writable = false;
    private $getCallback;
    private $setCallback;
    private $saveCallback;
    private $defaultCallback;
    private $filterable = false;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the location of the field within a JSON:API resource object
     * ('attributes' or 'relationships').
     */
    abstract public function getLocation(): string;

    /**
     * Set the description of the field for documentation generation.
     */
    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the model property to which this field corresponds.
     */
    public function property(string $property)
    {
        $this->property = $property;

        return $this;
    }

    /**
     * Allow this field to be seen.
     */
    public function visible(callable $condition = null)
    {
        $this->visible = $condition ?: true;

        return $this;
    }

    /**
     * Disallow this field to be seen.
     */
    public function hidden(callable $condition = null)
    {
        $this->visible = $condition ? negate($condition) : false;

        return $this;
    }

    /**
     * Only show this field on single root resources.
     *
     * This is useful if a field requires an expensive calculation for each
     * individual resource (eg. n+1 query problem). In this case it may be
     * desirable to only have the field show when viewing a single resource.
     */
    public function single()
    {
        $this->single = true;

        return $this;
    }

    /**
     * Allow this field to be written.
     */
    public function writable(callable $condition = null)
    {
        $this->writable = $condition ?: true;

        return $this;
    }

    /**
     * Disallow this field to be written.
     */
    public function readonly(callable $condition = null)
    {
        $this->writable = $condition ? negate($condition) : false;

        return $this;
    }

    /**
     * Define the value of this field.
     *
     * If null, the adapter will be used to get the value of this field.
     *
     * @param null|string|callable $value
     */
    public function get($value)
    {
        $this->getCallback = $value === null ? null : wrap($value);

        return $this;
    }

    /**
     * Set the callback to apply a new value for this field to the model.
     *
     * If null, the adapter will be used to set the field on the model.
     */
    public function set(?callable $callback)
    {
        $this->setCallback = $callback;

        return $this;
    }

    /**
     * Set the callback to save this field to the model.
     *
     * If specified, the adapter will NOT be used to set the field on the model.
     */
    public function save(?callable $callback)
    {
        $this->saveCallback = $callback;

        return $this;
    }

    /**
     * Run a callback after this field has been saved.
     */
    public function onSaved(callable $callback)
    {
        $this->listeners['saved'][] = $callback;

        return $this;
    }

    /**
     * Set a default value for this field to be used when creating a resource.
     *
     * @param null|string|callable $value
     */
    public function default($value)
    {
        $this->defaultCallback = wrap($value);

        return $this;
    }

    /**
     * Add a validation callback for this field.
     */
    public function validate(callable $callback)
    {
        $this->listeners['validate'][] = $callback;

        return $this;
    }

    /**
     * Allow this field to be used for filtering the resource listing.
     */
    public function filterable(callable $condition = null)
    {
        $this->filterable = $condition ?: true;

        return $this;
    }

    /**
     * Disallow this field to be used for filtering the resource listing.
     */
    public function notFilterable(callable $condition = null)
    {
        $this->filterable = $condition ? negate($condition) : false;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProperty(): ?string
    {
        return $this->property;
    }

    public function isVisible()
    {
        return $this->visible;
    }

    public function isSingle(): bool
    {
        return $this->single;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function getGetCallback()
    {
        return $this->getCallback;
    }

    public function getSetCallback(): ?callable
    {
        return $this->setCallback;
    }

    public function getSaveCallback(): ?callable
    {
        return $this->saveCallback;
    }

    public function getDefaultCallback()
    {
        return $this->defaultCallback;
    }

    public function isFilterable()
    {
        return $this->filterable;
    }
}
