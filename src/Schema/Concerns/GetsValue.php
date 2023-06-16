<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Closure;
use Tobyz\JsonApiServer\Context;

trait GetsValue
{
    protected ?Closure $getter = null;
    protected ?Closure $serializer = null;

    /**
     * Define the value of this field.
     *
     * If null, the adapter will be used to get the value of this field.
     */
    public function get(?Closure $getter): static
    {
        $this->getter = $getter;

        return $this;
    }

    /**
     * Apply a transformation to the value upon serialization.
     */
    public function serialize(?Closure $serializer): static
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Get the value of this field in the given context.
     */
    public function getValue(Context $context): mixed
    {
        if ($get = $this->getter) {
            return isset($context->model) ? $get($context->model, $context) : $get($context);
        }

        if (isset($context->model)) {
            return $context->resource->getValue($context->model, $this, $context);
        }

        return null;
    }

    /**
     * Serialize the value for JSON output.
     */
    public function serializeValue(mixed $value, Context $context): mixed
    {
        if ($this->nullable && $value === null) {
            return null;
        }

        if ($this->serializer) {
            $value = ($this->serializer)($value, $context);
        }

        return $value;
    }
}
