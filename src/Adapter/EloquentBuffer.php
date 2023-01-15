<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Adapter;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Relationship;

use function Tobyz\JsonApiServer\run_callbacks;

abstract class EloquentBuffer
{
    private static $buffer = [];

    public static function add(Model $model, string $relationName): void
    {
        static::$buffer[get_class($model)][$relationName][] = $model;
    }

    public static function load(Model $model, string $relationName, Relationship $relationship, Context $context): void
    {
        if (! $models = static::$buffer[get_class($model)][$relationName] ?? null) {
            return;
        }

        Collection::make($models)->load([
            $relationName => function ($relation) use ($model, $relationName, $relationship, $context) {
                $query = $relation->getQuery();

                // When loading the relationship, we need to scope the query
                // using the scopes defined in the related API resource – there
                // may be multiple if this is a polymorphic relationship. We
                // start by getting the resource types this relationship
                // could possibly contain.
                $resourceTypes = $context->getApi()->getResourceTypes();

                if ($type = $relationship->getType()) {
                    if (is_string($type)) {
                        $resourceTypes = [$resourceTypes[$type]];
                    } else {
                        $resourceTypes = array_intersect_key($resourceTypes, array_flip($type));
                    }
                }

                // Now, construct a map of model class names -> scoping
                // functions. This will be provided to the MorphTo::constrain
                // method in order to apply type-specific scoping.
                $constrain = [];

                foreach ($resourceTypes as $resourceType) {
                    if (
                        ($model = $resourceType->getAdapter()->model()) &&
                        !isset($constrain[get_class($model)])
                    ) {
                        $constrain[get_class($model)] = function ($query) use ($resourceType, $context) {
                            $resourceType->applyScopes($query, $context);
                        };
                    }
                }

                if ($relation instanceof MorphTo) {
                    $relation->constrain($constrain);
                } else {
                    reset($constrain)($query);
                }

                // Also apply any local scopes that have been defined on this
                // relationship.
                run_callbacks(
                    $relationship->getListeners('scope'),
                    [$query, $context]
                );
            }
        ]);

        static::$buffer[get_class($model)][$relationName] = [];
    }
}
