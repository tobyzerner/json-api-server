<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\Field\InvalidFieldValueException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\Type\NullViolationException;
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

        if ($value === null) {
            throw new JsonApiErrorsException([new NullViolationException()]);
        }

        if ($this->type) {
            $value = $this->deserializeTypeValue($value);
            $errors = [];

            // The schema describes the input, before custom deserialization changes its type.
            $this->type->validate($value, function ($error = []) use (&$errors) {
                $errors[] = $error instanceof ErrorProvider
                    ? $error
                    : new InvalidFieldValueException(is_scalar($error) ? ['detail' => (string) $error] : $error);
            });

            if ($errors) {
                throw new JsonApiErrorsException($errors);
            }
        }

        return parent::deserializeValue($value, $context);
    }

    protected function deserializeTypeValue(mixed $value): mixed
    {
        return $this->type->deserialize($value);
    }

    public function getSchema(SchemaContext $context): array
    {
        return parent::getSchema($context) + ($this->type?->schema() ?: []);
    }
}
