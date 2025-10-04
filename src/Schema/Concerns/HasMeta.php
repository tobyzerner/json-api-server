<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Attribute;

trait HasMeta
{
    /**
     * @var Attribute[]
     */
    public array $meta = [];

    /**
     * Define meta fields.
     */
    public function meta(array $fields): static
    {
        $this->meta = $fields;

        return $this;
    }

    public function serializeMeta(Context $context): array
    {
        $meta = [];

        foreach ($this->meta as $field) {
            if (!$field->isVisible($context)) {
                continue;
            }

            $value = $field->getValue($context);

            $meta[$field->name] = $field->serializeValue($value, $context);
        }

        return $meta;
    }
}
