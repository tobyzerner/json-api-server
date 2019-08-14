<?php

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\Field;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;

class MockAdapter implements AdapterInterface
{
    public $models = [];
    public $createdModel;
    private $type;

    public function __construct(array $models = [], string $type = null)
    {
        $this->models = $models;
        $this->type = $type;
    }

    public function create()
    {
        return $this->createdModel = (object) [];
    }

    public function query()
    {
        return (object) [];
    }

    public function find($query, $id)
    {
        return $this->models[$id] ?? (object) ['id' => $id];
    }

    public function get($query): array
    {
        return array_values($this->models);
    }

    public function getId($model): string
    {
        return $model->id;
    }

    public function getAttribute($model, Attribute $attribute)
    {
        return $model->{$this->getProperty($attribute)} ?? 'default';
    }

    public function getHasOne($model, HasOne $relationship)
    {
        return $model->{$this->getProperty($relationship)} ?? null;
    }

    public function getHasMany($model, HasMany $relationship): array
    {
        return $model->{$this->getProperty($relationship)} ?? [];
    }

    public function applyAttribute($model, Attribute $attribute, $value)
    {
        $model->{$this->getProperty($attribute)} = $value;
    }

    public function applyHasOne($model, HasOne $relationship, $related)
    {
        $model->{$this->getProperty($relationship)} = $related;
    }

    public function save($model)
    {
        $model->saveWasCalled = true;

        if (empty($model->id)) {
            $model->id = '1';
        }
    }

    public function saveHasMany($model, HasMany $relationship, array $related)
    {
        $model->saveHasManyWasCalled = true;
    }

    public function delete($model)
    {
        $model->deleteWasCalled = true;
    }

    public function filterByIds($query, array $ids)
    {
        $query->filters[] = ['ids', $ids];
    }

    public function filterByAttribute($query, Attribute $attribute, $value)
    {
        $query->filters[] = [$attribute, $value];
    }

    public function filterByHasOne($query, HasOne $relationship, array $ids)
    {
        $query->filters[] = [$relationship, $ids];
    }

    public function filterByHasMany($query, HasMany $relationship, array $ids)
    {
        $query->filters[] = [$relationship, $ids];
    }

    public function sortByAttribute($query, Attribute $attribute, string $direction)
    {
        $query->sort[] = [$attribute, $direction];
    }

    public function paginate($query, int $limit, int $offset)
    {
        $query->paginate[] = [$limit, $offset];
    }

    public function include($query, array $relationships)
    {
        $query->include[] = $relationships;
    }

    public function load(array $models, array $relationships)
    {
        foreach ($models as $model) {
            $model->load[] = $relationships;
        }
    }

    public function loadIds(array $models, Relationship $relationship)
    {
        foreach ($models as $model) {
            $model->loadIds[] = $relationship;
        }
    }

    private function getProperty(Field $field)
    {
        return $field->property ?: $field->name;
    }

    public function handles($model)
    {
        return isset($model['type']) && $model['type'] === $this->type;
    }
}
