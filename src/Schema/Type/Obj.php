<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use Tobyz\JsonApiServer\Exception\Type\AdditionalPropertyException;
use Tobyz\JsonApiServer\Exception\Type\RequiredPropertyException;
use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;

class Obj extends AbstractType
{
    private array $properties = [];
    private bool|Type|null $additionalProperties = null;

    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $this->mapValue($value, 'serialize');
    }

    protected function deserializeValue(mixed $value): mixed
    {
        return $this->mapValue($value, 'deserialize');
    }

    public function deserializeQueryValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $this->deserializeValue($value);
        }

        foreach ($value as $key => $item) {
            $propertyType = $this->properties[$key]['type'] ?? $this->additionalProperties;

            if (!$propertyType instanceof Type) {
                continue;
            }

            $value[$key] = $this->deserializeNestedQueryValue($propertyType, $item, $key);
        }

        return $value;
    }

    private function mapValue(mixed $value, string $method): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($this->properties as $name => $config) {
            if (array_key_exists($name, $value)) {
                $result[$name] = $config['type']->$method($value[$name]);
            }
        }

        foreach ($value as $key => $val) {
            if (isset($this->properties[$key])) {
                continue;
            }

            $result[$key] =
                $this->additionalProperties instanceof Type
                    ? $this->additionalProperties->$method($val)
                    : $val;
        }

        return $result;
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (!is_array($value)) {
            $fail(new TypeMismatchException('object', gettype($value)));
            return;
        }

        foreach ($this->properties as $name => $config) {
            if ($config['required'] && !array_key_exists($name, $value)) {
                $fail(new RequiredPropertyException($name));
                return;
            }
        }

        foreach ($this->properties as $name => $config) {
            if (array_key_exists($name, $value)) {
                $this->validateNestedValue($config['type'], $value[$name], $name, $fail);
            }
        }

        if ($this->additionalProperties === null) {
            return;
        }

        foreach ($value as $key => $val) {
            if (isset($this->properties[$key])) {
                continue;
            }

            if ($this->additionalProperties === false) {
                $fail(new AdditionalPropertyException($key));
                return;
            }

            if ($this->additionalProperties instanceof Type) {
                $this->validateNestedValue($this->additionalProperties, $val, $key, $fail);
            }
        }
    }

    protected function getSchema(): array
    {
        $schema = ['type' => 'object'];

        if (!empty($this->properties)) {
            $schema['properties'] = [];
            $required = [];

            foreach ($this->properties as $name => $config) {
                $schema['properties'][$name] = $config['type']->schema();
                if ($config['required']) {
                    $required[] = $name;
                }
            }

            if (!empty($required)) {
                $schema['required'] = $required;
            }
        }

        if ($this->additionalProperties === true) {
            $schema['additionalProperties'] = true;
        } elseif ($this->additionalProperties === false) {
            $schema['additionalProperties'] = false;
        } elseif ($this->additionalProperties instanceof Type) {
            $schema['additionalProperties'] = $this->additionalProperties->schema();
        }

        return $schema;
    }

    public function property(string $name, Type $type, bool $required = false): static
    {
        $this->properties[$name] = [
            'type' => $type,
            'required' => $required,
        ];

        return $this;
    }

    public function additionalProperties(bool|Type $allowed = true): static
    {
        $this->additionalProperties = $allowed;

        return $this;
    }
}
