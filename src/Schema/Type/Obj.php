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
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($this->properties as $name => $config) {
            if (array_key_exists($name, $value)) {
                $result[$name] = $config['type']->serialize($value[$name]);
            }
        }

        // Serialize additional properties if they're typed
        if ($this->additionalProperties instanceof Type) {
            foreach ($value as $key => $val) {
                if (!isset($this->properties[$key])) {
                    $result[$key] = $this->additionalProperties->serialize($val);
                }
            }
        } else {
            // Include untyped additional properties as-is
            foreach ($value as $key => $val) {
                if (!isset($this->properties[$key])) {
                    $result[$key] = $val;
                }
            }
        }

        return $result;
    }

    protected function deserializeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($this->properties as $name => $config) {
            if (array_key_exists($name, $value)) {
                $result[$name] = $config['type']->deserialize($value[$name]);
            }
        }

        // Deserialize additional properties if they're typed
        if ($this->additionalProperties instanceof Type) {
            foreach ($value as $key => $val) {
                if (!isset($this->properties[$key])) {
                    $result[$key] = $this->additionalProperties->deserialize($val);
                }
            }
        } else {
            // Include untyped additional properties as-is
            foreach ($value as $key => $val) {
                if (!isset($this->properties[$key])) {
                    $result[$key] = $val;
                }
            }
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
                $config['type']->validate($value[$name], $fail);
            }
        }

        if ($this->additionalProperties !== null) {
            foreach ($value as $key => $val) {
                if (!isset($this->properties[$key])) {
                    if ($this->additionalProperties === false) {
                        $fail(new AdditionalPropertyException($key));
                        return;
                    } elseif ($this->additionalProperties instanceof Type) {
                        $this->additionalProperties->validate($val, $fail);
                    }
                }
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
