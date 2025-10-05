<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use Tobyz\JsonApiServer\Exception\Type\InvalidSchemaException;

class OneOf extends AbstractType
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
        $passedCount = 0;

        foreach ($this->types as $type) {
            $typeFailed = false;
            $type->validate($value, function () use (&$typeFailed) {
                $typeFailed = true;
            });

            if (!$typeFailed) {
                $passedCount++;
            }
        }

        if ($passedCount !== 1) {
            $fail(new InvalidSchemaException('oneOf', $passedCount));
        }
    }

    protected function getSchema(): array
    {
        return [
            'oneOf' => array_map(fn($type) => $type->schema(), $this->types),
        ];
    }

    public function schema(): array
    {
        $schema = $this->getSchema();

        // For composition types, nullable is represented by adding {"type": "null"}
        // to the array per OpenAPI 3.1 spec
        if ($this->nullable) {
            $schema['oneOf'][] = ['type' => 'null'];
        }

        return $schema;
    }
}
