<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Schema\Concerns\GetsValue;
use Tobyz\JsonApiServer\Schema\Concerns\HasProperty;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Concerns\SetsValue;
use Tobyz\JsonApiServer\SchemaContext;

abstract class Field
{
    use HasProperty;
    use HasVisibility;
    use HasSchema;
    use GetsValue;
    use SetsValue;

    public bool $nullable = false;
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

    public function getSchema(SchemaContext $context): array
    {
        $schema = $this->schema;

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
