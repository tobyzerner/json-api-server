<?php

namespace Tobyz\JsonApiServer\Laravel\Concerns;

use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait QueriesRelationExistence
{
    /**
     * Constrain a query to models that have (or don't have) a related model
     * matching the given constraints, like whereHas() and whereDoesntHave().
     *
     * Belongs-to and belongs-to-many relationships are checked through their
     * foreign key or pivot table instead of joining the related table, as a
     * correlated EXISTS that joins the pivot to the related table can lead
     * MySQL to plans that scan whole tables. Constraints on the related query
     * go in an uncorrelated subquery on the related table, which is left out
     * if there are none. Related models are assumed to be referentially
     * intact: a dangling foreign key or pivot row matches as if its model
     * existed.
     *
     * @param ?Closure(Builder): void $constrain Constrains the related query.
     * @param ?Closure(Builder): void $constrainKeys Constrains the related
     *     query's key, such as with whereKey(). IN constraints on the key are
     *     checked against the foreign key or pivot column directly, as MySQL
     *     can turn an IN subquery back into a join with the related table.
     */
    protected function whereRelationExists(
        Builder $query,
        string $name,
        bool $exists,
        ?Closure $constrain,
        ?Closure $constrainKeys = null,
    ): void {
        $constrainAll = function (Builder $query) use ($constrain, $constrainKeys) {
            if ($constrain) {
                $constrain($query);
            }

            if ($constrainKeys) {
                $constrainKeys($query);
            }
        };

        $whereHas = fn(Relation|string $relation) => $query->{$exists
            ? 'whereHas'
            : 'whereDoesntHave'}($relation, $constrainAll);

        // Self-relations nested in whereHas() alias the parent table, which
        // qualified column names wouldn't refer to.
        if (
            str_contains($name, '.')
            || $query->getQuery()->from !== $query->getModel()->getTable()
        ) {
            $whereHas($name);
            return;
        }

        $relation = $query->getRelation($name);

        // Only the base relation classes, so that subclasses like MorphTo and
        // MorphToMany keep the constraints they add to existence queries.
        $columns = match (get_class($relation)) {
            BelongsTo::class => [
                $relation->getOwnerKeyName(),
                $relation->getQualifiedOwnerKeyName(),
                $relation->getQualifiedForeignKeyName(),
            ],
            BelongsToMany::class => [
                $relation->getRelatedKeyName(),
                $relation->getQualifiedRelatedKeyName(),
                $relation->getQualifiedRelatedPivotKeyName(),
            ],
            default => null,
        };

        $relationBase = $relation->getQuery()->getQuery();

        // Relations defined with their own constraints (such as wherePivot)
        // keep them by going through whereHas().
        if (
            $columns === null
            || $relationBase->wheres
            || count($relationBase->joins ?? []) !== ($relation instanceof BelongsToMany ? 1 : 0)
        ) {
            $whereHas($relation);
            return;
        }

        [$keyName, $key, $column] = $columns;

        $related = $relation
            ->getRelated()
            ->newQueryWithoutRelationships()
            ->mergeConstraintsFrom($relation->getQuery());

        if ($constrain) {
            $constrain($related);
        }

        $keyWheres = $constrainKeys
            ? $this->keyConstraints($related, [$keyName, $key], $constrainKeys)
            : [];

        $base = $related->toBase();

        // The related query can't be an IN subquery if it's limited (MySQL
        // doesn't support LIMIT in IN subqueries) or if it has clauses that
        // can refer to selected columns, which the subquery replaces. Such
        // queries go through whereHas(), which constrains them again.
        if ($base->limit !== null || $base->offset !== null || $base->unions || $base->havings) {
            $whereHas($relation);
            return;
        }

        // Without HAVING, grouping and ordering don't change which keys exist.
        $base->groups = null;
        $base->bindings['groupBy'] = [];

        $keys = $base->wheres || $base->joins ? $base->select($key)->reorder() : null;

        $constrainColumn = function ($query) use ($column, $keyWheres, $keys) {
            foreach ($keyWheres as $where) {
                $method = match ($where['type']) {
                    'In' => 'whereIn',
                    'NotIn' => 'whereNotIn',
                    'InRaw' => 'whereIntegerInRaw',
                    'NotInRaw' => 'whereIntegerNotInRaw',
                };

                $query->{$method}($column, $where['values']);
            }

            if ($keys) {
                $query->whereIn($column, $keys);
            }
        };

        if ($relation instanceof BelongsToMany) {
            $query->{$exists ? 'whereExists' : 'whereNotExists'}(
                fn(QueryBuilder $pivot) => $constrainColumn(
                    $pivot
                        ->selectRaw('1')
                        ->from($relation->getTable())
                        ->whereColumn(
                            $relation->getQualifiedForeignPivotKeyName(),
                            $relation->getQualifiedParentKeyName(),
                        ),
                ),
            );
            return;
        }

        // The condition is never NULL (the foreign key is checked and the
        // subquery has no NULLs), so it can be negated with whereNot().
        $keys?->whereNotNull($key);

        $query->{$exists ? 'where' : 'whereNot'}(
            fn(Builder $query) => $constrainColumn($query->whereNotNull($column)),
        );
    }

    /**
     * Get the IN constraints that a closure puts on the related key, to check
     * against the foreign key or pivot column. If it constrains the related
     * query in any other way, apply it to the related query instead.
     */
    private function keyConstraints(Builder $related, array $columns, Closure $constrainKeys): array
    {
        // Global scopes are registered (so the closure can remove them) but
        // aren't applied, so only the closure's constraints are seen.
        $query = $related->getModel()->newQueryWithoutRelationships();

        $constrainKeys($query);

        $base = $query->getQuery();

        $isKeyConstraint = fn(array $where) => (
            $where['boolean'] === 'and'
            && in_array($where['type'], ['In', 'NotIn', 'InRaw', 'NotInRaw'], true)
            && in_array($where['column'], $columns, true)
            && !array_filter($where['values'], fn($value) => $value instanceof Expression)
        );

        if (
            $query->removedScopes()
            || $base->joins
            || $base->groups
            || $base->havings
            || $base->limit !== null
            || array_filter($base->wheres, fn($where) => !$isKeyConstraint($where))
        ) {
            $constrainKeys($related);

            return [];
        }

        return $base->wheres;
    }
}
