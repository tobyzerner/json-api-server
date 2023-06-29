<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Schema\Concerns\GetsValue;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Concerns\SetsValue;

abstract class Field
{
    use HasVisibility;
    use GetsValue;
    use SetsValue;

    public ?string $property = null;
    public bool $nullable = false;
    public ?string $description = null;
    public array $schema = [];

    public function __construct(public readonly string $name)
    {
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function property(?string $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema + ['description' => $this->description, 'nullable' => $this->nullable];
    }
}
