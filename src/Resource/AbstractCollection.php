<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Pagination\Pagination;
use Tobyz\JsonApiServer\SchemaContext;

abstract class AbstractCollection implements Collection, ProvidesRootSchema
{
    public function endpoints(): array
    {
        return [];
    }

    public function rootSchema(SchemaContext $context): array
    {
        $schema = [];
        $context = $context->withCollection($this);

        foreach ($this->endpoints() as $endpoint) {
            if ($endpoint instanceof ProvidesRootSchema) {
                $schema = array_replace_recursive($schema, $endpoint->rootSchema($context));
            }
        }

        return $schema;
    }

    public function filters(): array
    {
        return [];
    }

    public function sorts(): array
    {
        return [];
    }

    public function defaultSort(): ?string
    {
        return null;
    }

    public function pagination(): ?Pagination
    {
        return null;
    }
}
