<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Resource\Updatable;

trait SetsValue
{
    public ?Closure $writable = null;
    public ?Closure $writableOnCreate = null;
    public ?Closure $required = null;
    public ?Closure $default = null;
    public ?Closure $deserializer = null;
    public ?Closure $setter = null;
    public ?Closure $saver = null;
    public array $validators = [];

    /**
     * Allow this field to be written to.
     */
    public function writable(?Closure $condition = null): static
    {
        $this->writable = $condition ?: fn() => true;

        return $this;
    }

    /**
     * Allow this field to be written to when creating a new model.
     */
    public function writableOnCreate(?Closure $condition = null): static
    {
        $this->writableOnCreate = $condition ?: fn() => true;

        return $this;
    }

    /**
     * Mark this field as required.
     */
    public function required(?Closure $condition = null): static
    {
        $this->required = $condition ?: fn() => true;

        return $this;
    }

    /**
     * Set a default value for this field.
     */
    public function default(mixed $default): static
    {
        if (!$default instanceof Closure) {
            $default = fn() => $default;
        }

        $this->default = $default;

        return $this;
    }

    /**
     * Apply a transformation to the value upon deserialization.
     */
    public function deserialize(?Closure $deserializer): static
    {
        $this->deserializer = $deserializer;

        return $this;
    }

    /**
     * Add a validator to the field.
     */
    public function validate(Closure $validator): static
    {
        $this->validators[] = $validator;

        return $this;
    }

    /**
     * Define a setter for this field.
     *
     * If null, the adapter will be used to set the value of this field.
     */
    public function set(?Closure $setter): static
    {
        $this->setter = $setter;

        return $this;
    }

    /**
     * Define a saver for this field, to be run after the model is saved.
     */
    public function save(?Closure $saver): static
    {
        $this->saver = $saver;

        return $this;
    }

    /**
     * Check if this field is writable in the given context.
     */
    public function isWritable(Context $context): bool
    {
        return $this->writable && ($this->writable)($context->model, $context);
    }

    /**
     * Check if this field is writable when creating a resource.
     */
    public function isWritableOnCreate(Context $context): bool
    {
        return $this->isWritable($context) ||
            ($this->writableOnCreate && ($this->writableOnCreate)($context->model, $context));
    }

    /**
     * Check if this field is required.
     */
    public function isRequired(): bool
    {
        return $this->required && ($this->required)();
    }

    /**
     * Deserialize a JSON value to an internal representation.
     */
    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if ($this->nullable && $value === null) {
            return null;
        }

        if ($this->deserializer) {
            return ($this->deserializer)($value, $context);
        }

        return $value;
    }

    /**
     * Validate a value for this field.
     */
    public function validateValue(mixed $value, callable $fail, Context $context): void
    {
        if ($value === null && !$this->nullable) {
            $fail('must not be null');
        }

        foreach ($this->validators as $validator) {
            $validator($value, $fail, $context);
        }
    }

    /**
     * Set a value for this field to a model.
     */
    public function setValue(mixed $model, mixed $value, Context $context): void
    {
        if ($this->setter) {
            ($this->setter)($model, $value, $context);
        } elseif (
            !$this->saver &&
            ($context->resource instanceof Creatable || $context->resource instanceof Updatable)
        ) {
            $context->resource->setValue($model, $this, $value, $context);
        }
    }

    /**
     * Save a value for this field to a model.
     */
    public function saveValue(mixed $model, mixed $value, Context $context): void
    {
        if ($this->saver) {
            ($this->saver)($model, $value, $context);
        } elseif (
            $context->resource instanceof Creatable ||
            $context->resource instanceof Updatable
        ) {
            $context->resource->saveValue($model, $this, $value, $context);
        }
    }
}
