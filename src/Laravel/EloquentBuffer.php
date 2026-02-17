<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\Concerns\ScopesRelatedResourceQueries;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

abstract class EloquentBuffer
{
    use ScopesRelatedResourceQueries;

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
            $relationName => fn($relation) => static::scopeRelatedQuery(
                $relationship,
                $relation,
                $relation->getQuery(),
                $context,
            ),
        ]);

        static::$buffer[get_class($model)][$relationName] = [];
    }
}
