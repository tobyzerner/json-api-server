<?php

namespace Tobscure\JsonApiServer\Adapter;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tobscure\JsonApiServer\Schema\Attribute;
use Tobscure\JsonApiServer\Schema\HasMany;
use Tobscure\JsonApiServer\Schema\HasOne;
use Tobscure\JsonApiServer\Schema\Relationship;

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
            throw new \InvalidArgumentException('Model must be an instance of '.Model::class);
        }
    }

    public function create()
    {
        return $this->model->newInstance();
    }

    public function query()
    {
        return $this->model->query();
    }

    public function find($query, $id)
    {
        return $query->find($id);
    }

    public function get($query): array
    {
        return $query->get()->all();
    }

    public function count($query): int
    {
        return $query->count();
    }

    public function getId($model): string
    {
        return $model->getKey();
    }

    public function getAttribute($model, Attribute $field)
    {
        return $model->{$this->getAttributeProperty($field)};
    }

    public function getHasOneId($model, HasOne $field)
    {
        $relation = $model->{$this->getRelationshipProperty($field)}();

        if ($relation instanceof BelongsTo) {
            $related = $relation->getRelated();

            $key = $model->{$relation->getForeignKeyName()};

            if ($key) {
                return $related->forceFill([$related->getKeyName() => $key]);
            }

            return null;
        }

        return $model->{$this->getRelationshipProperty($field)};
    }

    public function getHasOne($model, HasOne $field)
    {
        return $model->{$this->getRelationshipProperty($field)};
    }

    public function getHasMany($model, HasMany $field): array
    {
        $collection = $model->{$this->getRelationshipProperty($field)};

        return $collection ? $collection->all() : [];
    }

    public function applyAttribute($model, Attribute $field, $value)
    {
        $model->{$this->getAttributeProperty($field)} = $value;
    }

    public function applyHasOne($model, HasOne $field, $related)
    {
        $model->{$this->getRelationshipProperty($field)}()->associate($related);
    }

    public function save($model)
    {
        $model->save();
    }

    public function saveHasMany($model, HasMany $field, array $related)
    {
        $model->{$this->getRelationshipProperty($field)}()->sync(Collection::make($related));
    }

    public function delete($model)
    {
        $model->delete();
    }

    public function filterByIds($query, array $ids)
    {
        $key = $query->getModel()->getQualifiedKeyName();

        $query->whereIn($key, $ids);
    }

    public function filterByAttribute($query, Attribute $field, $value)
    {
        $property = $this->getAttributeProperty($field);

        if (preg_match('/(.+)\.\.(.+)/', $value, $matches)) {
            if ($matches[1] !== '*') {
                $query->where($property, '>=', $matches[1]);
            }
            if ($matches[2] !== '*') {
                $query->where($property, '<=', $matches[2]);
            }

            return;
        }

        foreach (['>=', '>', '<=', '<'] as $operator) {
            if (strpos($value, $operator) === 0) {
                $query->where($property, $operator, substr($value, strlen($operator)));

                return;
            }
        }

        $query->where($property, $value);
    }

    public function filterByHasOne($query, HasOne $field, array $ids)
    {
        $relation = $query->getModel()->{$this->getRelationshipProperty($field)}();

        $foreignKey = $relation->getQualifiedForeignKeyName();

        $query->whereIn($foreignKey, $ids);
    }

    public function filterByHasMany($query, HasMany $field, array $ids)
    {
        $property = $this->getRelationshipProperty($field);
        $relation = $query->getModel()->{$property}();
        $relatedKey = $relation->getRelated()->getQualifiedKeyName();

        $query->whereHas($property, function ($query) use ($relatedKey, $ids) {
            $query->whereIn($relatedKey, $ids);
        });
    }

    public function sortByAttribute($query, Attribute $field, string $direction)
    {
        $query->orderBy($this->getAttributeProperty($field), $direction);
    }

    public function paginate($query, int $limit, int $offset)
    {
        $query->take($limit)->skip($offset);
    }

    public function load(array $models, array $trail)
    {
        (new Collection($models))->load($this->relationshipTrailToPath($trail));
    }

    public function loadIds(array $models, Relationship $relationship)
    {
        if (empty($models)) {
            return;
        }

        $property = $this->getRelationshipProperty($relationship);
        $relation = $models[0]->$property();

        if ($relation instanceof BelongsTo) {
            return;
        }

        (new Collection($models))->load([
            $property => function ($query) use ($relation) {
                $query->select([
                    $relation->getRelated()->getKeyName(),
                    $relation->getForeignKeyName()
                ]);
            }
        ]);
    }

    private function getAttributeProperty(Attribute $field)
    {
        return $field->property ?: strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field->name));
    }

    private function getRelationshipProperty(Relationship $field)
    {
        return $field->property ?: $field->name;
    }

    private function relationshipTrailToPath(array $trail)
    {
        return implode('.', array_map(function ($relationship) {
            return $this->getRelationshipProperty($relationship);
        }, $trail));
    }
}
