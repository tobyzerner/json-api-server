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

use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasListeners;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use function Tobyz\JsonApiServer\negate;
use function Tobyz\JsonApiServer\wrap;

abstract class Field
{
    use HasListeners;
    use HasDescription;
    use HasVisibility;

    private $name;
    private $property;
    private $writable = false;
    private $writableOnce = false;
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
     * Set the model property to which this field corresponds.
     */
    public function property(string $property)
    {
        $this->property = $property;

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
     * Only allow this field to be written on creation.
     */
    public function once()
    {
        $this->writableOnce = true;

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
     * Apply a transformation to the value before it is set on the model.
     */
    public function transform(callable $callback)
    {
        $this->listeners['transform'][] = $callback;

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
    public function saved(callable $callback)
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

    public function getWritable()
    {
        return $this->writable;
    }

    public function isWritableOnce(): bool
    {
        return $this->writableOnce;
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

    public function getFilterable()
    {
        return $this->filterable;
    }
}
