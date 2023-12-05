<?php

namespace Tobyz\JsonApiServer\Schema\Type;

class Arr implements Type
{
    private int $minItems = 0;
    private ?int $maxItems = null;
    private bool $uniqueItems = false;
    private ?Type $items = null;

    public static function make(): static
    {
        return new static();
    }

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function deserialize(mixed $value): mixed
    {
        return $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        if (!is_array($value)) {
            $fail('must be an array');
            return;
        }

        if (count($value) < $this->minItems) {
            $fail(sprintf('must contain at least %d values', $this->minItems));
        }

        if ($this->maxItems !== null && count($value) > $this->maxItems) {
            $fail(sprintf('must contain no more than %d values', $this->maxItems));
        }

        if ($this->uniqueItems && count($value) !== count(array_unique($value))) {
            $fail('must contain unique values');
        }

        if ($this->items) {
            foreach ($value as $item) {
                $this->items->validate($item, $fail);
            }
        }
    }

    public function schema(): array
    {
        return [
            'type' => 'array',
            'minItems' => $this->minItems,
            'maxItems' => $this->maxItems,
            'uniqueItems' => $this->uniqueItems,
            'items' => $this->items?->schema(),
        ];
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
