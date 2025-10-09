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
    private ?Type $items = null;

    public static function make(): static
    {
        return new static();
    }

    protected function serializeValue(mixed $value): mixed
    {
        return $value;
    }

    protected function deserializeValue(mixed $value): mixed
    {
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

        if ($this->uniqueItems && count($value) !== count(array_unique($value))) {
            $fail(new UniqueViolationException());
        }

        if ($this->items) {
            foreach ($value as $i => $item) {
                $itemErrors = [];

                $this->items->validate($item, function ($error) use (&$itemErrors) {
                    $itemErrors[] = $error;
                });

                foreach ($itemErrors as $itemError) {
                    $fail($itemError->prependSource(['pointer' => "/$i"]));
                }
            }
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

    public function items(?Type $type): static
    {
        $this->items = $type;

        return $this;
    }
}
