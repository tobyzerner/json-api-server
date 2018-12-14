<?php

namespace Tobscure\JsonApiServer\Adapter;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Tobscure\JsonApiServer\Schema\Attribute;
use Tobscure\JsonApiServer\Schema\HasMany;
use Tobscure\JsonApiServer\Schema\HasOne;

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

    public function getId($model): string
    {
        return $model->getKey();
    }

    public function getAttribute($model, Attribute $field)
    {
        return $model->{$field->property};
    }

    public function getHasOne($model, HasOne $field)
    {
        return $model->{$field->property};
    }

    public function getHasMany($model, HasMany $field): array
    {
        return $model->{$field->property}->all();
    }

    public function applyAttribute($model, Attribute $field, $value)
    {
        $model->{$field->property} = $value;
    }

    public function applyHasOne($model, HasOne $field, $related)
    {
        $model->{$field->property}()->associate($related);
    }

    public function save($model)
    {
        $model->save();
    }

    public function saveHasMany($model, HasMany $field, array $related)
    {
        $model->{$field->property}()->sync(Collection::make($related));
    }

    public function delete($model)
    {
        $model->delete();
    }

    public function filterByAttribute($query, Attribute $field, $value)
    {
        $query->where($field->property, $value);
    }

    public function filterByHasOne($query, HasOne $field, array $ids)
    {
        $property = $field->property;
        $foreignKey = $query->getModel()->{$property}()->getQualifiedForeignKey();

        $query->whereIn($foreignKey, $ids);
    }

    public function filterByHasMany($query, HasMany $field, array $ids)
    {
        $property = $field->property;
        $relation = $query->getModel()->{$property}();
        $relatedKey = $relation->getRelated()->getQualifiedKeyName();

        $query->whereHas($property, function ($query) use ($relatedKey, $ids) {
            $query->whereIn($relatedKey, $ids);
        });
    }

    public function sortByAttribute($query, Attribute $field, string $direction)
    {
        $query->orderBy($field->property, $direction);
    }

    public function paginate($query, int $limit, int $offset)
    {
        $query->take($limit)->skip($offset);
    }

    public function include($query, array $trail)
    {
        $query->with($this->relationshipTrailToPath($trail));
    }

    public function load($model, array $trail)
    {
        $model->load($this->relationshipTrailToPath($trail));
    }

    private function relationshipTrailToPath(array $trail)
    {
        return implode('.', array_map(function ($relationship) {
            return $relationship->property;
        }, $trail));
    }
}
