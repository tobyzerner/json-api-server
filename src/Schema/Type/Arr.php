<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use Tobyz\JsonApiServer\Exception\Type\RangeViolationException;
use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;
use Tobyz\JsonApiServer\Exception\Type\UniqueViolationException;

class Arr extends AbstractType
{
    private int $minItems = 0;
    private ?int $maxItems = null;
    private bool $uniqueItems = false;
    private bool $commaSeparated = false;
    public ?Type $items = null;

    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $this->mapItems($value, fn($item) => $this->items->serialize($item));
    }

    protected function deserializeValue(mixed $value): mixed
    {
        return $this->mapItems($value, fn($item) => $this->items->deserialize($item));
    }

    public function deserializeQueryValue(mixed $value): mixed
    {
        if ($this->commaSeparated) {
            $value = $this->splitCommaSeparated($value);
        }

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        if ($this->items) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->deserializeNestedQueryValue($this->items, $item, $key);
            }
        }

        return $value;
    }

    private function splitCommaSeparated(mixed $value): mixed
    {
        if (is_string($value)) {
            return explode(',', $value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                $result[] = $item;
                continue;
            }

            foreach (explode(',', $item) as $part) {
                $result[] = $part;
            }
        }

        return $result;
    }

    private function mapItems(mixed $value, callable $callback): mixed
    {
        if (!is_array($value) || !$this->items) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $callback($item);
        }

        return $value;
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (!is_array($value)) {
            $fail(new TypeMismatchException('array', gettype($value)));
            return;
        }

        if (count($value) < $this->minItems) {
            $fail(new RangeViolationException('minItems', $this->minItems, count($value)));
        }

        if ($this->maxItems !== null && count($value) > $this->maxItems) {
            $fail(new RangeViolationException('maxItems', $this->maxItems, count($value)));
        }

        if ($this->uniqueItems && count($value) !== count(array_unique($value, SORT_REGULAR))) {
            $fail(new UniqueViolationException());
        }

        if (!$this->items) {
            return;
        }

        foreach ($value as $i => $item) {
            $this->validateNestedValue($this->items, $item, $i, $fail);
        }
    }

    protected function getSchema(): array
    {
        $schema = ['type' => 'array'];

        if ($this->minItems) {
            $schema['minItems'] = $this->minItems;
        }

        if ($this->maxItems !== null) {
            $schema['maxItems'] = $this->maxItems;
        }

        if ($this->uniqueItems) {
            $schema['uniqueItems'] = $this->uniqueItems;
        }

        if ($this->commaSeparated) {
            $schema['x-jsonapi-filter-comma-separated'] = true;
        }

        if ($this->items) {
            $schema['items'] = $this->items->schema();
        }

        return $schema;
    }

    public function minItems(int $minItems): static
    {
        $this->minItems = $minItems;

        return $this;
    }

    public function maxItems(?int $maxItems): static
    {
        $this->maxItems = $maxItems;

        return $this;
    }

    public function uniqueItems(bool $uniqueItems = true): static
    {
        $this->uniqueItems = $uniqueItems;

        return $this;
    }

    public function commaSeparated(bool $commaSeparated = true): static
    {
        $this->commaSeparated = $commaSeparated;

        return $this;
    }

    public function items(?Type $type): static
    {
        $this->items = $type;

        return $this;
    }
}
