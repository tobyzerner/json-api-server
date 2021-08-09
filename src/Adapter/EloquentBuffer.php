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

    public static function add(Model $model, string $relation): void
    {
        static::$buffer[get_class($model)][$relation][] = $model;
    }

    public static function load(Model $model, string $relation, Relationship $relationship, Context $context): void
    {
        if (! $models = static::$buffer[get_class($model)][$relation] ?? null) {
            return;
        }

        Collection::make($models)->loadMissing([
            $relation => function ($query) use ($model, $relation, $relationship, $context) {
                // As we're loading the relationship, we need to scope the query
                // using the scopes defined in the related API resources. We
                // start by getting the resource types this relationship
                // could contain.
                $resourceTypes = $context->getApi()->getResourceTypes();

                if ($type = $relationship->getType()) {
                    if (is_string($type)) {
                        $resourceTypes = [$resourceTypes[$type]];
                    } else {
                        $resourceTypes = array_intersect_key($resourceTypes, array_flip($type));
                    }
                }

                $constrain = [];

                foreach ($resourceTypes as $resourceType) {
                    if ($model = $resourceType->getAdapter()->model()) {
                        $constrain[get_class($model)] = function ($query) use ($resourceType, $context) {
                            run_callbacks(
                                $resourceType->getSchema()->getListeners('scope'),
                                [$query, $context]
                            );
                        };
                    }
                }

                if ($query instanceof MorphTo) {
                    $query->constrain($constrain);
                } else {
                    reset($constrain)($query->getQuery());
                }

                // Also apply relationship scopes to the query.
                run_callbacks(
                    $relationship->getListeners('scope'),
                    [$query->getQuery(), $context]
                );
            }
        ]);

        static::$buffer[get_class($model)][$relation] = [];
    }
}
