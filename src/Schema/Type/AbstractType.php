<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use Tobyz\JsonApiServer\Exception\Type\NullViolationException;

abstract class AbstractType implements Type
{
    protected bool $nullable = false;

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function serialize(mixed $value): mixed
    {
        if ($value === null && $this->nullable) {
            return null;
        }

        return $this->serializeValue($value);
    }

    public function deserialize(mixed $value): mixed
    {
        if ($value === null && $this->nullable) {
            return null;
        }

        return $this->deserializeValue($value);
    }

    public function validate(mixed $value, callable $fail): void
    {
        if ($value === null) {
            if (!$this->nullable) {
                $fail(new NullViolationException());
            }
            return;
        }

        $this->validateValue($value, $fail);
    }

    public function schema(): array
    {
        $schema = $this->getSchema();

        if ($this->nullable) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    abstract protected function serializeValue(mixed $value): mixed;

    abstract protected function deserializeValue(mixed $value): mixed;

    abstract protected function validateValue(mixed $value, callable $fail): void;

    abstract protected function getSchema(): array;
}
