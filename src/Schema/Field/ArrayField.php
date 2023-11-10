<?php

namespace Tobyz\JsonApiServer\Schema\Field;

class ArrayField extends Field
{
    private int $minItems = 0;
    private ?int $maxItems = null;
    private bool $uniqueItems = false;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->validate(function (mixed $value, callable $fail): void {
            if ($value === null) {
                return;
            }

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

            if ($this->uniqueItems && $value !== array_unique($value)) {
                $fail('must contain unique values');
            }
        });
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
}
