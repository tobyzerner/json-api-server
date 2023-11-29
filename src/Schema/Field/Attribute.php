<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type\TypeInterface;

class Attribute extends Field
{
    public ?TypeInterface $type = null;

    public function type(?TypeInterface $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function serializeValue(mixed $value, Context $context): mixed
    {
        if ($this->nullable && $value === null) {
            return null;
        }

        if ($this->type) {
            $value = $this->type->serialize($value);
        }

        return parent::serializeValue($value, $context);
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
}
