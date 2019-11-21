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

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
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

    public function create()
    {
        return $this->model->newInstance();
    }

    public function query()
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
        return $query->getQuery()->getCountForPagination();
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
        $this->getEloquentRelation($model, $relationship)->associate($related);
    }

    public function save($model): void
    {
        $model->save();
    }

    public function saveHasMany($model, HasMany $relationship, array $related): void
    {
        $this->getEloquentRelation($model, $relationship)->sync(new Collection($related));
    }

    public function delete($model): void
    {
        // For models that use the SoftDeletes trait, deleting the resource from
        // the API implies permanent deletion. Non-permanent deletion should be
        // achieved by manipulating a resource attribute.
        if (method_exists($model, 'forceDelete')) {
            $model->forceDelete();
        } else {
            $model->delete();
        }
    }

    public function filterByIds($query, array $ids): void
    {
        $key = $query->getModel()->getQualifiedKeyName();

        $query->whereIn($key, $ids);
    }

    public function filterByAttribute($query, Attribute $attribute, $value): void
    {
        $column = $this->getAttributeColumn($attribute);

        // TODO: extract this into non-adapter territory
        if (preg_match('/(.+)\.\.(.+)/', $value, $matches)) {
            if ($matches[1] !== '*') {
                $query->where($column, '>=', $matches[1]);
            }
            if ($matches[2] !== '*') {
                $query->where($column, '<=', $matches[2]);
            }

            return;
        }

        foreach (['>=', '>', '<=', '<'] as $operator) {
            if (strpos($value, $operator) === 0) {
                $query->where($column, $operator, substr($value, strlen($operator)));

                return;
            }
        }

        $query->where($column, $value);
    }

    public function filterByHasOne($query, HasOne $relationship, array $ids): void
    {
        $relation = $this->getEloquentRelation($query->getModel(), $relationship);

        $query->whereIn($relation->getQualifiedForeignKeyName(), $ids);
    }

    public function filterByHasMany($query, HasMany $relationship, array $ids): void
    {
        $property = $this->getRelationshipProperty($relationship);
        $relation = $this->getEloquentRelation($query->getModel(), $relationship);
        $relatedKey = $relation->getRelated()->getQualifiedKeyName();

        $query->whereHas($property, function ($query) use ($relatedKey, $ids) {
            $query->whereIn($relatedKey, $ids);
        });
    }

    public function sortByAttribute($query, Attribute $attribute, string $direction): void
    {
        $query->orderBy($this->getAttributeColumn($attribute), $direction);
    }

    public function paginate($query, int $limit, int $offset): void
    {
        $query->take($limit)->skip($offset);
    }

    public function load(array $models, array $relationships, Closure $scope, bool $linkage): void
    {
        // TODO: Find the relation on the model that we're after. If it's a
        // belongs-to relation, and we only need linkage, then we won't need
        // to load anything as the related ID is store directly on the model.

        (new Collection($models))->loadMissing([
            $this->getRelationshipPath($relationships) => $scope
        ]);
    }

    // public function loadIds(array $models, Relationship $relationship): void
    // {
    //     if (empty($models)) {
    //         return;
    //     }
    //
    //     $property = $this->getRelationshipProperty($relationship);
    //     $relation = $models[0]->$property();
    //
    //     // If it's a belongs-to relationship, then the ID is stored on the model
    //     // itself, so we don't need to load anything in advance.
    //     if ($relation instanceof BelongsTo) {
    //         return;
    //     }
    //
    //     (new Collection($models))->loadMissing([
    //         $property => function ($query) use ($relation) {
    //             $query->select($relation->getRelated()->getKeyName());
    //
    //             if (! $relation instanceof BelongsToMany) {
    //                 $query->addSelect($relation->getForeignKeyName());
    //             }
    //         }
    //     ]);
    // }

    private function getAttributeProperty(Attribute $attribute): string
    {
        return $attribute->getProperty() ?: strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $attribute->getName()));
    }

    private function getAttributeColumn(Attribute $attribute): string
    {
        return $this->model->getTable().'.'.$this->getAttributeProperty($attribute);
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
