<?php

namespace Tobscure\JsonApiServer\Adapter;

use Tobscure\JsonApiServer\Schema\Attribute;
use Tobscure\JsonApiServer\Schema\HasMany;
use Tobscure\JsonApiServer\Schema\HasOne;

interface AdapterInterface
{
    public function create();

    public function query();

    public function find($query, $id);

    public function get($query): array;

    public function getId($model): string;

    public function getAttribute($model, Attribute $attribute);

    public function getHasOne($model, HasOne $relationship);

    public function getHasMany($model, HasMany $relationship): array;

    public function applyAttribute($model, Attribute $attribute, $value);

    public function applyHasOne($model, HasOne $relationship, $related);

    public function save($model);

    public function saveHasMany($model, HasMany $relationship, array $related);

    public function delete($model);

    public function filterByAttribute($query, Attribute $attribute, $value);

    public function filterByHasOne($query, HasOne $relationship, array $ids);

    public function filterByHasMany($query, HasMany $relationship, array $ids);

    public function sortByAttribute($query, Attribute $attribute, string $direction);

    public function paginate($query, int $limit, int $offset);

    public function include($query, array $relationships);

    public function load($model, array $relationships);
}
