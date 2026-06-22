<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\SchemaContext;

trait AppliesType
{
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
            $valid = true;

            $this->type->validate($value, function ($error = []) use ($fail, &$valid) {
                $valid = false;
                $fail($error);
            });

            if (!$valid) {
                return;
            }
        }

        parent::validateValue($value, $fail, $context);
    }

    public function getSchema(SchemaContext $context): array
    {
        return parent::getSchema($context) + ($this->type?->schema() ?: []);
    }
}
