<?php

namespace Tobyz\JsonApiServer\Adapter;

use Closure;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;

class NullAdapter implements AdapterInterface
{
    public function query()
    {
    }

    public function filterByIds($query, array $ids): void
    {
    }

    public function filterByAttribute($query, Attribute $attribute, $value, string $operator = '='): void
    {
    }

    public function filterByRelationship($query, Relationship $relationship, Closure $scope): void
    {
    }

    public function sortByAttribute($query, Attribute $attribute, string $direction): void
    {
    }

    public function paginate($query, int $limit, int $offset): void
    {
    }

    public function find($query, string $id)
    {
    }

    public function get($query): array
    {
        return [];
    }

    public function count($query): int
    {
        return 0;
    }

    public function getId($model): string
    {
        return '';
    }

    public function getAttribute($model, Attribute $attribute)
    {
    }

    public function getHasOne($model, HasOne $relationship, bool $linkageOnly, Context $context)
    {
    }

    public function getHasMany($model, HasMany $relationship, bool $linkageOnly, Context $context)
    {
    }

    public function represents($model): bool
    {
        return false;
    }

    public function model()
    {
    }

    public function setId($model, string $id): void
    {
    }

    public function setAttribute($model, Attribute $attribute, $value): void
    {
    }

    public function setHasOne($model, HasOne $relationship, $related): void
    {
    }

    public function save($model): void
    {
    }

    public function saveHasMany($model, HasMany $relationship, array $related): void
    {
    }

    public function delete($model): void
    {
    }
}
