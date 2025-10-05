<?php

namespace Tobyz\JsonApiServer\Schema\Type;

use Tobyz\JsonApiServer\Exception\Type\InvalidSchemaException;

class Not extends AbstractType
{
    public function __construct(private Type $type)
    {
    }

    public static function make(Type $type): static
    {
        return new static($type);
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
        $typeFailed = false;
        $this->type->validate($value, function () use (&$typeFailed) {
            $typeFailed = true;
        });

        if (!$typeFailed) {
            $fail(new InvalidSchemaException('not'));
        }
    }

    protected function getSchema(): array
    {
        return [
            'not' => $this->type->schema(),
        ];
    }
}
