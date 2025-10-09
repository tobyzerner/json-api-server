<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\SchemaContext;

trait HasSchema
{
    protected array $schema = [];

    /**
     * Set custom OpenAPI schema to merge with the base schema.
     */
    public function schema(array $schema): static
    {
        $this->schema = array_merge_recursive($this->schema, $schema);

        return $this;
    }

    /**
     * Set the description of the field for documentation generation.
     */
    public function description(?string $description): static
    {
        $this->schema['description'] = $description;

        return $this;
    }

    /**
     * Get the custom schema.
     */
    private function getSchema(SchemaContext $context): array
    {
        return $this->schema;
    }

    /**
     * Merge custom schema with the base OpenAPI schema.
     */
    private function mergeSchema(array $baseSchema): array
    {
        return array_replace_recursive($baseSchema, $this->schema);
    }
}
