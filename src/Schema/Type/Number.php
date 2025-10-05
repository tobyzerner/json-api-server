<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use InvalidArgumentException;
use Tobyz\JsonApiServer\Exception\Type\MultipleViolationException;
use Tobyz\JsonApiServer\Exception\Type\RangeViolationException;
use Tobyz\JsonApiServer\Exception\Type\TypeMismatchException;

class Number extends AbstractType
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

    protected function serializeValue(mixed $value): mixed
    {
        return (float) $value;
    }

    protected function deserializeValue(mixed $value): mixed
    {
        return $value;
    }

    protected function validateValue(mixed $value, callable $fail): void
    {
        if (!is_numeric($value)) {
            $fail(new TypeMismatchException('number', gettype($value)));
            return;
        }

        if ($this->minimum !== null) {
            if ($this->exclusiveMinimum && $value <= $this->minimum) {
                $fail(
                    new RangeViolationException('minimum', $this->minimum, $value, exclusive: true),
                );
            } elseif ($value < $this->minimum) {
                $fail(new RangeViolationException('minimum', $this->minimum, $value));
            }
        }

        if ($this->maximum !== null) {
            if ($this->exclusiveMaximum && $value >= $this->maximum) {
                $fail(
                    new RangeViolationException('maximum', $this->maximum, $value, exclusive: true),
                );
            } elseif ($value > $this->maximum) {
                $fail(new RangeViolationException('maximum', $this->maximum, $value));
            }
        }

        // Divide the value by multipleOf instead of using the modulo operator to avoid bugs when using a multipleOf
        // that has decimal places. (Since the modulo operator converts the multipleOf to int)
        // Note that dividing two integers returns another integer if the result is a whole number. So to make the
        // comparison work at all times we need to cast the result to float. Casting both to integer will not work
        // as intended since then the result of the division would also be rounded.
        if (
            $this->multipleOf !== null &&
            (float) ($value / $this->multipleOf) !== round($value / $this->multipleOf)
        ) {
            $fail(new MultipleViolationException($this->multipleOf, $value));
        }
    }

    protected function getSchema(): array
    {
        $schema = ['type' => 'number'];

        if ($this->minimum !== null) {
            $schema['minimum'] = $this->minimum;
        }

        if ($this->exclusiveMinimum) {
            $schema['exclusiveMinimum'] = $this->exclusiveMinimum;
        }

        if ($this->maximum !== null) {
            $schema['maximum'] = $this->maximum;
        }

        if ($this->exclusiveMaximum) {
            $schema['exclusiveMaximum'] = $this->exclusiveMaximum;
        }

        if ($this->multipleOf !== null) {
            $schema['multipleOf'] = $this->multipleOf;
        }

        return $schema;
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
        if ($number !== null && $number <= 0) {
            throw new InvalidArgumentException('multipleOf must be a positive number');
        }

        $this->multipleOf = $number;

        return $this;
    }
}
