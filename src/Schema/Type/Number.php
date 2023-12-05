<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use InvalidArgumentException;

class Number implements Type
{
    private ?float $minimum = null;
    private bool $exclusiveMinimum = false;
    private ?float $maximum = null;
    private bool $exclusiveMaximum = false;
    private ?float $multipleOf = null;

    public static function make(): static
    {
        return new static();
    }

    public function serialize(mixed $value): mixed
    {
        return (float) $value;
    }

    public function deserialize(mixed $value): mixed
    {
        return $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        if (!is_numeric($value)) {
            $fail('must be numeric');
            return;
        }

        if ($this->minimum !== null) {
            if ($this->exclusiveMinimum && $value <= $this->minimum) {
                $fail(sprintf('must be greater than %d', $this->minimum));
            } elseif ($value < $this->minimum) {
                $fail(sprintf('must be greater than or equal to %d', $this->minimum));
            }
        }

        if ($this->maximum !== null) {
            if ($this->exclusiveMaximum && $value >= $this->maximum) {
                $fail(sprintf('must be less than %d', $this->maximum));
            } elseif ($value > $this->maximum) {
                $fail(sprintf('must be less than or equal to %d', $this->maximum));
            }
        }

        if ($this->multipleOf !== null && $value % $this->multipleOf !== 0) {
            $fail(sprintf('must be a multiple of %d', $this->multipleOf));
        }
    }

    public function schema(): array
    {
        return [
            'type' => 'number',
            'minimum' => $this->minimum,
            'exclusiveMinimum' => $this->exclusiveMinimum,
            'maximum' => $this->maximum,
            'exclusiveMaximum' => $this->exclusiveMaximum,
            'multipleOf' => $this->multipleOf,
        ];
    }

    public function minimum(?float $minimum, bool $exclusive = false): static
    {
        $this->minimum = $minimum;
        $this->exclusiveMinimum = $exclusive;

        return $this;
    }

    public function maximum(?float $maximum, bool $exclusive = false): static
    {
        $this->maximum = $maximum;
        $this->exclusiveMaximum = $exclusive;

        return $this;
    }

    public function multipleOf(?float $number): static
    {
        if ($number <= 0) {
            throw new InvalidArgumentException('multipleOf must be a positive number');
        }

        $this->multipleOf = $number;

        return $this;
    }
}
