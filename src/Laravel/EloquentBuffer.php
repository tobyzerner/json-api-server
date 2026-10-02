<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Laravel\Concerns\ScopesRelatedResourceQueries;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use WeakMap;

abstract class EloquentBuffer
{
    use ScopesRelatedResourceQueries;

    private static array $buffer = [];
    private static ?WeakMap $linkage = null;

    public static function add(Model $model, string $relationName, Context $context): void
    {
        // Linkage-only loads are buffered separately, so that scopes which only
        // load IDs for linkage don't apply to models that need full resources.
        static::$buffer[get_class($model)][$relationName][$context->linkageOnly][spl_object_id($model)] = $model;
    }

    public static function load(Model $model, string $relationName, Relationship $relationship, Context $context): void
    {
        if (!($models = static::$buffer[get_class($model)][$relationName][$context->linkageOnly] ?? null)) {
            return;
        }

        static::$buffer[get_class($model)][$relationName][$context->linkageOnly] = [];

        $constrain = [
            $relationName => fn($relation) => static::scopeRelatedQuery(
                $relationship,
                $relation,
                $relation->getQuery(),
                $context,
            ),
        ];

        // Scopes may only load IDs for linkage, so load linkage-only relations
        // onto clones, keeping partial resources off the models where other
        // fields would see them.
        $targets = $context->linkageOnly
            ? array_map(fn(Model $buffered) => clone $buffered, $models)
            : $models;

        Collection::make($targets)->load($constrain);

        if (!$context->linkageOnly) {
            return;
        }

        static::$linkage ??= new WeakMap();

        foreach ($models as $id => $buffered) {
            static::$linkage[$buffered] ??= [];
            static::$linkage[$buffered][$relationName] = $targets[$id]->getRelation($relationName);
        }
    }

    public static function get(Model $model, string $relationName, Context $context): mixed
    {
        if ($context->linkageOnly) {
            return static::$linkage[$model][$relationName] ?? null;
        }

        return $model->getRelation($relationName);
    }
}
