<?php

namespace Tobyz\JsonApiServer\Schema\Type;

class Str implements Type
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

    public function serialize(mixed $value): string
    {
        return (string) $value;
    }

    public function deserialize(mixed $value): mixed
    {
        return $value;
    }

    public function validate(mixed $value, callable $fail): void
    {
        if (!is_string($value)) {
            $fail('must be a string');
            return;
        }

        if ($this->enum !== null && !in_array($value, $this->enum, true)) {
            $enum = array_map(fn($value) => '"' . $value . '"', $this->enum);
            $fail(sprintf('must be one of %s', implode(', ', $enum)));
        }

        if (strlen($value) < $this->minLength) {
            $fail(sprintf('must be at least %d characters', $this->minLength));
        }

        if ($this->maxLength !== null && strlen($value) > $this->maxLength) {
            $fail(sprintf('must be no more than %d characters', $this->maxLength));
        }

        if (
            $this->pattern &&
            !preg_match('/' . str_replace('/', '\/', $this->pattern) . '/', $value)
        ) {
            $fail(sprintf('must match the pattern %s', $this->pattern));
        }
    }

    public function schema(): array
    {
        return [
            'type' => 'string',
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'pattern' => $this->pattern,
            'format' => $this->format,
            'enum' => $this->enum,
        ];
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
}
