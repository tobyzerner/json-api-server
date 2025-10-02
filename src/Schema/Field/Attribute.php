<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Type\Type;

class Attribute extends Field
{
    public ?Type $type = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function location(): ?string
    {
        return 'attributes';
    }

    public function type(?Type $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function serializeValue(mixed $value, Context $context): mixed
    {
        if ($this->nullable && $value === null) {
            return null;
        }

        $value = parent::serializeValue($value, $context);

        if ($this->type) {
            $value = $this->type->serialize($value);
        }

        return $value;
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        if ($this->nullable && $value === null) {
            return null;
        }

        if ($this->type) {
            $value = $this->type->deserialize($value);
        }

        return parent::deserializeValue($value, $context);
    }

    public function validateValue(mixed $value, callable $fail, Context $context): void
    {
        if ($value !== null && $this->type) {
            $this->type->validate($value, $fail);
        }

        parent::validateValue($value, $fail, $context);
    }

    public function getSchema(JsonApi $api): array
    {
        return parent::getSchema($api) + ($this->type?->schema() ?: []);
    }
}
