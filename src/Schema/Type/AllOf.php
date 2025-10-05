<?php

namespace Tobyz\JsonApiServer\Schema\Type;

class AllOf extends AbstractType
{
    /**
     * @param Type[] $types
     */
    public function __construct(private array $types)
    {
    }

    public static function make(array $types): static
    {
        return new static($types);
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
        foreach ($this->types as $type) {
            $type->validate($value, $fail);
        }
    }

    protected function getSchema(): array
    {
        return [
            'allOf' => array_map(fn($type) => $type->schema(), $this->types),
        ];
    }
}
