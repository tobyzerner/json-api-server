<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\SchemaContext;

trait BuildsRelationshipPaths
{
    private function relationshipPaths(SchemaContext $context, callable $buildPath): array
    {
        $type = $context->collection->name();
        $paths = [];

        foreach ($context->collection->resources() as $resourceName) {
            $resource = $context->resource($resourceName);
            $resourceContext = $context->withResource($resource);

            foreach ($resource->fields() as $field) {
                if (!$field instanceof Relationship) {
                    continue;
                }

                $definition = $buildPath($type, $resource, $field, $resourceContext);

                if ($definition === null) {
                    continue;
                }

                [$path, $operations] = $definition;
                $paths[$path] = $operations;
            }
        }

        return ['paths' => $paths];
    }
}
