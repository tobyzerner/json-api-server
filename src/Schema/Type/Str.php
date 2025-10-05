<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use BackedEnum;
use Tobyz\JsonApiServer\Exception\Type\EnumViolationException;
use Tobyz\JsonApiServer\Exception\Type\PatternViolationException;
use Tobyz\JsonApiServer\Exception\Type\RangeViolationException;
use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;
use UnitEnum;

class Str extends AbstractType
{
    public int $minLength = 0;
    public ?int $maxLength = null;
    public ?string $pattern = null;
    public ?string $format = null;
    public ?array $enum = null;

    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): string
    {
        if ($value instanceof UnitEnum) {
            return $this->getEnumValue($value);
        }

        return (string) $value;
    }

    protected function deserializeValue(mixed $value): mixed
    {
        return $value;
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (!is_string($value)) {
            $fail(new TypeMismatchException('string', gettype($value)));
            return;
        }

        if ($this->enum !== null) {
            $enumValues = array_map($this->getEnumValue(...), $this->enum);
            if (!in_array($value, $enumValues, true)) {
                $fail(new EnumViolationException($enumValues, $value));
            }
        }

        if (strlen($value) < $this->minLength) {
            $fail(new RangeViolationException('minLength', $this->minLength, strlen($value)));
        }

        if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
            $fail(new RangeViolationException('maxLength', $this->maxLength, strlen($value)));
        }

        if (
            $this->pattern &&
            !preg_match('/' . str_replace('/', '\/', $this->pattern) . '/', $value)
        ) {
            $fail(new PatternViolationException($this->pattern));
        }
    }

    protected function getSchema(): array
    {
        $schema = ['type' => 'string'];

        if ($this->minLength) {
            $schema['minLength'] = $this->minLength;
        }

        if ($this->maxLength !== null) {
            $schema['maxLength'] = $this->maxLength;
        }

        if ($this->pattern !== null) {
            $schema['pattern'] = $this->pattern;
        }

        if ($this->format !== null) {
            $schema['format'] = $this->format;
        }

        if ($this->enum !== null) {
            $schema['enum'] = array_map($this->getEnumValue(...), $this->enum);
            $schema['x-enum-varnames'] = array_map($this->getEnumName(...), $this->enum);
        }

        return $schema;
    }

    public function minLength(int $characters): static
    {
        $this->minLength = $characters;

        return $this;
    }

    public function maxLength(?int $characters): static
    {
        $this->maxLength = $characters;

        return $this;
    }

    public function pattern(?string $pattern): static
    {
        $this->pattern = $pattern;

        return $this;
    }

    public function format(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function enum(?array $enum): static
    {
        $this->enum = $enum;

        return $this;
    }

    private function getEnumValue(string|UnitEnum $value): string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    private function getEnumName(string|UnitEnum $value): string
    {
        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }
}
