<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use DomainException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Type\Str;

class Id extends Field
{
    public Str $type;

    public function __construct()
    {
        parent::__construct('id');

        $this->type(Str::make());
        $this->nullable = false;
    }

    public static function make(): static
    {
        return new static();
    }

    public function type(Str $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function serializeValue(mixed $value, Context $context): mixed
    {
        $value = parent::serializeValue($value, $context);

        return $this->type->serialize($value);
    }

    public function deserializeValue(mixed $value, Context $context): mixed
    {
        $value = $this->type->deserialize($value);

        return parent::deserializeValue($value, $context);
    }

    public function validateValue(mixed $value, callable $fail, Context $context): void
    {
        $this->type->validate($value, $fail);

        parent::validateValue($value, $fail, $context);
    }

    public function getSchema(JsonApi $api): array
    {
        return parent::getSchema($api) + ($this->type->schema() ?: []);
    }

    public function writable(?Closure $condition = null): static
    {
        throw new DomainException('ID cannot be writable. Use writeableOnCreate() instead.');
    }

    public function nullable(bool $nullable = true): static
    {
        throw new DomainException('ID cannot be nullable.');
    }
}
