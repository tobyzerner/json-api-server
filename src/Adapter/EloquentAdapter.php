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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use InvalidArgumentException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;

class EloquentAdapter implements AdapterInterface
{
    /**
     * @var Model
     */
    protected $model;

    public function __construct($model)
    {
        $this->model = is_string($model) ? new $model : $model;

        if (! $this->model instanceof Model) {
            throw new InvalidArgumentException('Model must be an instance of '.Model::class);
        }
    }

    public function represents($model): bool
    {
        return $model instanceof $this->model;
    }

    public function newModel()
    {
        return $this->model->newInstance();
    }

    public function newQuery(Context $context)
    {
        return $this->model->query();
    }

    public function find($query, string $id)
    {
        return $query->find($id);
    }

    public function get($query): array
    {
        return $query->get()->all();
    }

    public function count($query): int
    {
        return $query->toBase()->getCountForPagination();
    }

    public function getId($model): string
    {
        return $model->getKey();
    }

    public function getAttribute($model, Attribute $attribute)
    {
        return $model->{$this->getAttributeProperty($attribute)};
    }

    public function getHasOne($model, HasOne $relationship, bool $linkage)
    {
        // If it's a belongs-to relationship and we only need to get the ID,
        // then we don't have to actually load the relation because the ID is
        // stored in a column directly on the model. We will mock up a related
        // model with the value of the ID filled.
        if ($linkage) {
            $relation = $this->getEloquentRelation($model, $relationship);

            if ($relation instanceof BelongsTo) {
                if ($key = $model->{$relation->getForeignKeyName()}) {
                    $related = $relation->getRelated();

                    return $related->newInstance()->forceFill([$related->getKeyName() => $key]);
                }

                return null;
            }
        }

        return $this->getRelationValue($model, $relationship);
    }

    public function getHasMany($model, HasMany $relationship, bool $linkage): array
    {
        $collection = $this->getRelationValue($model, $relationship);

        return $collection ? $collection->all() : [];
    }

    public function setAttribute($model, Attribute $attribute, $value): void
    {
        $model->{$this->getAttributeProperty($attribute)} = $value;
    }

    public function setHasOne($model, HasOne $relationship, $related): void
    {
        $relation = $this->getEloquentRelation($model, $relationship);

        if ($relation instanceof BelongsTo) {
            if ($related === null) {
                $relation->dissociate();
            } else {
                $relation->associate($related);
            }
        }
    }

    public function save($model): void
    {
        $model->save();
    }

    public function saveHasMany($model, HasMany $relationship, array $related): void
    {
        $relation = $this->getEloquentRelation($model, $relationship);

        if ($relation instanceof BelongsToMany) {
            $relation->sync(new Collection($related));
        }
    }

    public function delete($model): void
    {
        // For models that use the SoftDeletes trait, deleting the resource from
        // the API implies permanent deletion. Non-permanent deletion should be
        // achieved by manipulating a resource attribute.
        $model->forceDelete();
    }

    public function filterByIds($query, array $ids): void
    {
        $key = $query->getModel()->getQualifiedKeyName();

        $query->whereIn($key, $ids);
    }

    public function filterByAttribute($query, Attribute $attribute, $value, string $operator = '='): void
    {
        $column = $this->getAttributeColumn($attribute);

        $query->where($column, $operator, $value);
    }

    public function filterByHasOne($query, HasOne $relationship, array $ids): void
    {
        $relation = $this->getEloquentRelation($query->getModel(), $relationship);

        if ($relation instanceof HasOneThrough) {
            $query->whereHas($this->getRelationshipProperty($relationship), function ($query) use ($relation, $ids) {
                $query->whereIn($relation->getQualifiedParentKeyName(), $ids);
            });
        } else {
            $query->whereIn($relation->getQualifiedForeignKeyName(), $ids);
        }
    }

    public function filterByHasMany($query, HasMany $relationship, array $ids): void
    {
        $property = $this->getRelationshipProperty($relationship);
        $relation = $this->getEloquentRelation($query->getModel(), $relationship);
        $relatedKey = $relation->getRelated()->getQualifiedKeyName();

        if (count($ids)) {
            foreach ($ids as $id) {
                $query->whereHas($property, function ($query) use ($relatedKey, $id) {
                    $query->where($relatedKey, $id);
                });
            }
        } else {
            $query->whereDoesntHave($property);
        }
    }

    public function sortByAttribute($query, Attribute $attribute, string $direction): void
    {
        $query->orderBy($this->getAttributeColumn($attribute), $direction);
    }

    public function paginate($query, int $limit, int $offset): void
    {
        $query->take($limit)->skip($offset);
    }

    public function load(array $models, array $relationships, $scope, bool $linkage): void
    {
        // TODO: Find the relation on the model that we're after. If it's a
        // belongs-to relation, and we only need linkage, then we won't need
        // to load anything as the related ID is store directly on the model.

        (new Collection($models))->loadMissing([
            $this->getRelationshipPath($relationships) => function ($relation) use ($relationships, $scope) {
                $query = $relation->getQuery();

                if (is_array($scope)) {
                    // TODO: since https://github.com/laravel/framework/pull/35190
                    // was merged, we can now apply loading constraints to
                    // polymorphic relationships.
                } else {
                    $scope($query);
                }
            }
        ]);
    }

    private function getAttributeProperty(Attribute $attribute): string
    {
        return $attribute->getProperty() ?: strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $attribute->getName()));
    }

    private function getAttributeColumn(Attribute $attribute): string
    {
        return $this->getAttributeProperty($attribute);
    }

    private function getRelationshipProperty(Relationship $relationship): string
    {
        return $relationship->getProperty() ?: $relationship->getName();
    }

    private function getRelationshipPath(array $trail): string
    {
        return implode('.', array_map([$this, 'getRelationshipProperty'], $trail));
    }

    private function getEloquentRelation($model, Relationship $relationship)
    {
        return $model->{$this->getRelationshipProperty($relationship)}();
    }

    private function getRelationValue($model, Relationship $relationship)
    {
        return $model->{$this->getRelationshipProperty($relationship)};
    }
}
