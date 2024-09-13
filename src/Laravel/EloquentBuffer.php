<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\Field\ToMany;
use Tobyz\JsonApiServer\Laravel\Field\ToOne;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

abstract class EloquentBuffer
{
    private static array $buffer = [];

    public static function add(Model $model, string $relationName): void
    {
        static::$buffer[get_class($model)][$relationName][] = $model;
    }

    public static function load(
        Model $model,
        string $relationName,
        Relationship $relationship,
        Context $context,
    ): void {
        if (!($models = static::$buffer[get_class($model)][$relationName] ?? null)) {
            return;
        }

        Collection::make($models)->load([
            $relationName => function ($relation) use (
                $model,
                $relationName,
                $relationship,
                $context,
            ) {
                $query = $relation->getQuery();

                // When loading the relationship, we need to scope the query
                // using the scopes defined in the related API resource – there
                // may be multiple if this is a polymorphic relationship. We
                // start by getting the resource types this relationship
                // could possibly contain.
                $resources = $context->api->resources;

                if ($type = $relationship->collections) {
                    $resources = array_intersect_key($resources, array_flip($type));
                }

                // Now, construct a map of model class names -> scoping
                // functions. This will be provided to the MorphTo::constrain
                // method in order to apply type-specific scoping.
                $constrain = [];

                foreach ($resources as $resource) {
                    $modelClass = get_class($resource->newModel($context));

                    if ($resource instanceof EloquentResource && !isset($constrain[$modelClass])) {
                        $constrain[$modelClass] = function ($query) use (
                            $resource,
                            $context,
                            $relationship,
                            $relation
                        ) {
                            $resource->scope($query, $context);

                            if (
                                ($relationship instanceof ToMany ||
                                    $relationship instanceof ToOne) &&
                                $relationship->scope
                            ) {
                                ($relationship->scope)($relation, $context);
                            }
                        };
                    }
                }

                if ($relation instanceof MorphTo) {
                    $relation->constrain($constrain);
                } elseif ($constrain) {
                    reset($constrain)($query);
                }
            },
        ]);

        static::$buffer[get_class($model)][$relationName] = [];
    }
}
