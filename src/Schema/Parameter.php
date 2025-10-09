<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Type\Type;
use Tobyz\JsonApiServer\SchemaContext;

class Parameter extends Field
{
    public ?Type $type = null;
    public string $in = 'query'; // 'query', 'path', 'header'

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

    public function in(string $in): static
    {
        $this->in = $in;

        return $this;
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

    public function getSchema(SchemaContext $context): array
    {
        $schema = [
            'name' => $this->name,
            'in' => $this->in,
            'schema' => $this->type?->schema() ?: (object) [],
            ...parent::getSchema($context),
        ];

        if ($this->required) {
            $schema['required'] = true;
        }

        return $schema;
    }
}
