<?php

namespace Tobyz\JsonApiServer\Laravel\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\EloquentResource;
use Tobyz\JsonApiServer\Laravel\Field\ToMany;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

trait ScopesRelatedResourceQueries
{
    protected static function scopeRelatedQuery(
        Relationship $relationship,
        object $relation,
        object $query,
        Context $context,
    ): void {
        $applyRelationshipScope = function () use ($relationship, $relation, $context): void {
            if (
                ($relationship instanceof ToMany || $relationship instanceof ToOne) &&
                $relationship->scope
            ) {
                ($relationship->scope)($relation, $context);
            }
        };

        $constrain = [];

        foreach ($relationship->collections as $collection) {
            foreach ($context->api->getCollection($collection)->resources() as $resourceName) {
                $resource = $context->api->getResource($resourceName);

                if (!$resource instanceof EloquentResource) {
                    continue;
                }

                $modelClass = get_class($resource->newModel($context));

                if (isset($constrain[$modelClass])) {
                    continue;
                }

                $constrain[$modelClass] = function ($query) use (
                    $resource,
                    $context,
                    $applyRelationshipScope,
                ) {
                    $resource->scope($query, $context);
                    $applyRelationshipScope();
                };
            }
        }

        if ($relation instanceof MorphTo) {
            $relation->constrain($constrain);
        } elseif ($constrain) {
            $relatedModelClass = get_class($query->getModel());
            ($constrain[$relatedModelClass] ?? reset($constrain))($query);
        } else {
            $applyRelationshipScope();
        }
    }
}
