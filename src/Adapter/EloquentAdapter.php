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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Deferred;
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

    public function query(): Builder
    {
        return $this->model->query();
    }

    public function filterByIds($query, array $ids): void
    {
        $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
    }

    public function filterByAttribute($query, Attribute $attribute, $value, string $operator = '='): void
    {
        $query->where($this->getAttributeProperty($attribute), $operator, $value);
    }

    public function filterByRelationship($query, Relationship $relationship, Closure $scope): void
    {
        $query->whereHas($this->getRelationshipProperty($relationship), $scope);
    }

    public function sortByAttribute($query, Attribute $attribute, string $direction): void
    {
        $query->orderBy($this->getAttributeProperty($attribute), $direction);
    }

    public function paginate($query, int $limit, int $offset): void
    {
        $query->take($limit)->skip($offset);
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
        return $model->getAttribute($this->getAttributeProperty($attribute));
    }

    public function getHasOne($model, HasOne $relationship, bool $linkageOnly, Context $context)
    {
        // If this is a belongs-to relationship, and we only need to get the ID
        // for linkage, then we don't have to actually load the relation because
        // the ID is stored in a column directly on the model. We will mock up a
        // related model with the value of the ID filled.
        if ($linkageOnly) {
            $relation = $this->getEloquentRelation($model, $relationship);

            if ($relation instanceof BelongsTo) {
                if ($key = $model->getAttribute($relation->getForeignKeyName())) {
                    $related = $relation->getRelated();

                    return $related->newInstance()->forceFill([
                        $related->getKeyName() => $key
                    ]);
                }

                return null;
            }
        }

        return $this->getRelationship($model, $relationship, $context);
    }

    public function getHasMany($model, HasMany $relationship, bool $linkageOnly, Context $context)
    {
        return $this->getRelationship($model, $relationship, $context);
    }

    protected function getRelationship($model, Relationship $relationship, Context $context): Deferred
    {
        $name = $this->getRelationshipProperty($relationship);

        EloquentBuffer::add($model, $name);

        return new Deferred(function () use ($model, $name, $relationship, $context) {
            EloquentBuffer::load($model, $name, $relationship, $context);

            $data = $model->getRelation($name);

            return $data instanceof Collection ? $data->all() : $data;
        });
    }

    public function represents($model): bool
    {
        return $model instanceof $this->model;
    }

    public function model(): Model
    {
        return $this->model->newInstance();
    }

    public function setId($model, string $id): void
    {
        $model->setAttribute($model->getKeyName(), $id);
    }

    public function setAttribute($model, Attribute $attribute, $value): void
    {
        $model->setAttribute($this->getAttributeProperty($attribute), $value);
    }

    public function setHasOne($model, HasOne $relationship, $related): void
    {
        $relation = $this->getEloquentRelation($model, $relationship);

        // If this is a belongs-to relationship, then the ID is stored on the
        // model itself so we can set it here.
        if ($relation instanceof BelongsTo) {
            $relation->associate($related);
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

    private function getAttributeProperty(Attribute $attribute): string
    {
        return $attribute->getProperty() ?: strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $attribute->getName()));
    }

    private function getRelationshipProperty(Relationship $relationship): string
    {
        return $relationship->getProperty() ?: $relationship->getName();
    }

    private function getEloquentRelation($model, Relationship $relationship)
    {
        return $model->{$this->getRelationshipProperty($relationship)}();
    }
}
