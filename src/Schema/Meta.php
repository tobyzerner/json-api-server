<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\JsonApiServer\SchemaContext;

class Meta extends Field
{
    public ?Type $type = null;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function location(): ?string
    {
        return null;
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

    public function getSchema(SchemaContext $context): array
    {
        return parent::getSchema($context) + ($this->type?->schema() ?: []);
    }
}
