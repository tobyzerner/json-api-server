<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Meta;

use function Tobyz\JsonApiServer\resolve_value;

trait HasMeta
{
    /**
     * @var Meta[]
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
        return $this->serializeFieldValues($this->meta, $context);
    }

    /**
     * Serialize the values of the visible fields, keyed by name.
     */
    protected function serializeFieldValues(array $fields, Context $context): array
    {
        $values = [];

        foreach ($fields as $field) {
            if (!$field->isVisible($context)) {
                continue;
            }

            $value = resolve_value($field->getValue($context));

            $values[$field->name] = $field->serializeValue($value, $context);
        }

        return $values;
    }
}
