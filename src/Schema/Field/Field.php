<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Schema\Concerns\GetsValue;
use Tobyz\JsonApiServer\Schema\Concerns\HasProperty;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Concerns\SetsValue;

abstract class Field
{
    use HasProperty;
    use HasVisibility;
    use GetsValue;
    use SetsValue;

    public bool $nullable = false;
    public ?string $description = null;
    public array $schema = [];
    public bool $sparse = false;

    public function __construct(public readonly string $name)
    {
    }

    public static function location(): ?string
    {
        return null;
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

    public function getSchema(JsonApi $api): array
    {
        $schema = $this->schema;

        if ($this->description) {
            $schema['description'] = $this->description;
        }

        if ($this->nullable) {
            $schema['nullable'] = $this->nullable;
        }

        return $schema;
    }

    /**
     * Only include this field if it is specifically requested.
     */
    public function sparse(): static
    {
        $this->sparse = true;

        return $this;
    }
}
