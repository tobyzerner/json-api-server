<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

trait HasSchema
{
    private array $schema = [];

    /**
     * Set custom OpenAPI schema to merge with the base schema.
     */
    public function schema(array $schema): static
    {
        $this->schema = array_merge_recursive($this->schema, $schema);

        return $this;
    }

    /**
     * Merge custom schema with the base OpenAPI schema.
     */
    private function mergeSchema(array $baseSchema): array
    {
        return array_merge_recursive($baseSchema, $this->schema);
    }
}
