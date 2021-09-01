<?php

namespace Tobyz\Tests\JsonApiServer;

use Closure;
use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\Field;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;

class MockAdapter implements AdapterInterface
{
    public $models = [];
    public $createdModel;
    public $query;
    private $type;

    public function __construct(array $models = [], string $type = null)
    {
        $this->models = $models;
        $this->type = $type;
    }

    public function model()
    {
        return $this->createdModel = (object) [];
    }

    public function query()
    {
        return $this->query = (object) [];
    }

    public function find($query, string $id)
    {
        if ($id === '404') {
            return null;
        }

        return $this->models[$id] ?? (object) ['id' => $id];
    }

    public function get($query): array
    {
        $results = array_values($this->models);

        if (isset($query->paginate)) {
            $results = array_slice($results, $query->paginate['offset'], $query->paginate['limit']);
        }

        return $results;
    }

    public function getId($model): string
    {
        return $model->id;
    }

    public function getAttribute($model, Attribute $attribute)
    {
        return $model->{$this->getProperty($attribute)} ?? 'default';
    }

    public function getHasOne($model, HasOne $relationship, bool $linkageOnly, Context $context)
    {
        return $model->{$this->getProperty($relationship)} ?? null;
    }

    public function getHasMany($model, HasMany $relationship, bool $linkageOnly, Context $context): array
    {
        return $model->{$this->getProperty($relationship)} ?? [];
    }

    public function setId($model, string $id): void
    {
        $model->id = $id;
    }

    public function setAttribute($model, Attribute $attribute, $value): void
    {
        $model->{$this->getProperty($attribute)} = $value;
    }

    public function setHasOne($model, HasOne $relationship, $related): void
    {
        $model->{$this->getProperty($relationship)} = $related;
    }

    public function save($model): void
    {
        $model->saveWasCalled = true;

        if (empty($model->id)) {
            $model->id = '1';
        }
    }

    public function saveHasMany($model, HasMany $relationship, array $related): void
    {
        $model->saveHasManyWasCalled = true;
    }

    public function delete($model): void
    {
        $model->deleteWasCalled = true;
    }

    public function filterByIds($query, array $ids): void
    {
        $query->filter[] = ['ids', $ids];
    }

    public function filterByAttribute($query, Attribute $attribute, $value, string $operator = '='): void
    {
        $query->filter[] = [$attribute, $operator, $value];
    }

    public function filterByRelationship($query, Relationship $relationship, Closure $scope): void
    {
        $query->filter[] = [$relationship, $scope];
    }

    public function sortByAttribute($query, Attribute $attribute, string $direction): void
    {
        $query->sort[] = [$attribute, $direction];
    }

    public function paginate($query, int $limit, int $offset): void
    {
        $query->paginate = compact('limit', 'offset');
    }

    public function load(array $models, array $relationships, $scope, bool $linkage): void
    {
        if (is_array($scope)) {
            foreach ($scope as $type => $apply) {
                $apply((object) []);
            }
        } else {
            $scope((object) []);
        }

        foreach ($models as $model) {
            $model->load[] = $relationships;
        }
    }

    private function getProperty(Field $field): string
    {
        return $field->getProperty() ?: $field->getName();
    }

    public function represents($model): bool
    {
        return isset($model['type']) && $model['type'] === $this->type;
    }

    public function count($query): int
    {
        return count($this->models);
    }
}
