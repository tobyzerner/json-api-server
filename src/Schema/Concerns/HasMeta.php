<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Closure;
use Tobyz\JsonApiServer\Context;

trait HasMeta
{
    public array $meta = [];

    /**
     * Define meta fields.
     */
    public function meta(array $fields): static
    {
        $this->meta = $fields;

        return $this;
    }

    protected function serializeMeta(Context $context): array
    {
        $meta = [];

        foreach ($this->meta as $field) {
            if (!$field->isVisible($context)) {
                continue;
            }

            $value = $field->getValue($context);

            $meta[$field->name] = $value instanceof Closure ? $value() : $value;
        }

        return $meta;
    }
}
