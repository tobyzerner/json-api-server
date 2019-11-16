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
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Relationship;

interface AdapterInterface
{
    /**
     * Create a new query builder instance.
     *
     * This is used as a basis for building the queries which show a resource
     * or list a resource index. It will be passed around through the relevant
     * scopes, filters, and sorting methods before finally being passed into
     * the `find` or `get` methods.
     *
     * @return mixed
     */
    public function query();

    /**
     * Manipulate the query to only include resources with the given IDs.
     *
     * @param $query
     * @param array $ids
     * @return mixed
     */
    public function filterByIds($query, array $ids): void;

    /**
     * Manipulate the query to only include resources with a certain attribute
     * value.
     *
     * @param $query
     * @param Attribute $attribute
     * @param $value
     * @return mixed
     */
    public function filterByAttribute($query, Attribute $attribute, $value): void;

    /**
     * Manipulate the query to only include resources with any one of the given
     * resource IDs in a has-one relationship.
     *
     * @param $query
     * @param HasOne $relationship
     * @param array $ids
     * @return mixed
     */
    public function filterByHasOne($query, HasOne $relationship, array $ids): void;

    /**
     * Manipulate the query to only include resources with any one of the given
     * resource IDs in a has-many relationship.
     *
     * @param $query
     * @param HasMany $relationship
     * @param array $ids
     * @return mixed
     */
    public function filterByHasMany($query, HasMany $relationship, array $ids): void;

    /**
     * Manipulate the query to sort by the given attribute in the given direction.
     *
     * @param $query
     * @param Attribute $attribute
     * @param string $direction
     * @return mixed
     */
    public function sortByAttribute($query, Attribute $attribute, string $direction): void;

    /**
     * Manipulate the query to only include a certain number of results,
     * starting from the given offset.
     *
     * @param $query
     * @param int $limit
     * @param int $offset
     * @return mixed
     */
    public function paginate($query, int $limit, int $offset): void;

    /**
     * Find a single resource by ID from the query.
     *
     * @param $query
     * @param string $id
     * @return mixed
     */
    public function find($query, string $id);

    /**
     * Get a list of resources from the query.
     *
     * @param $query
     * @return array
     */
    public function get($query): array;

    /**
     * Get the number of results from the query.
     *
     * @param $query
     * @return int
     */
    public function count($query): int;

    /**
     * Determine whether or not this resource type represents the given model.
     *
     * This is used for polymorphic relationships, where there are one or many
     * related models of unknown type. The first resource type with an adapter
     * that responds positively from this method will be used.
     *
     * @param mixed $model
     * @return bool
     */
    public function represents($model): bool;

    /**
     * Create a new model instance.
     *
     * @return mixed
     */
    public function create();

    /**
     * Get the ID from the model.
     *
     * @param $model
     * @return string
     */
    public function getId($model): string;

    /**
     * Get the value of an attribute from the model.
     *
     * @param $model
     * @param Attribute $attribute
     * @return mixed
     */
    public function getAttribute($model, Attribute $attribute);

    /**
     * Get the model for a has-one relationship for the model.
     *
     * @param $model
     * @param HasOne $relationship
     * @param bool $linkage
     * @return mixed|null
     */
    public function getHasOne($model, HasOne $relationship, bool $linkage);

    /**
     * Get a list of models for a has-many relationship for the model.
     *
     * @param $model
     * @param HasMany $relationship
     * @param bool $linkage
     * @return array
     */
    public function getHasMany($model, HasMany $relationship, bool $linkage): array;

    /**
     * Apply an attribute value to the model.
     *
     * @param $model
     * @param Attribute $attribute
     * @param $value
     * @return mixed
     */
    public function setAttribute($model, Attribute $attribute, $value): void;

    /**
     * Apply a has-one relationship value to the model.
     *
     * @param $model
     * @param HasOne $relationship
     * @param $related
     * @return mixed
     */
    public function setHasOne($model, HasOne $relationship, $related): void;

    /**
     * Save the model.
     *
     * @param $model
     * @return mixed
     */
    public function save($model): void;

    /**
     * Save a has-many relationship for the model.
     *
     * @param $model
     * @param HasMany $relationship
     * @param array $related
     * @return mixed
     */
    public function saveHasMany($model, HasMany $relationship, array $related): void;

    /**
     * Delete the model.
     *
     * @param $model
     * @return mixed
     */
    public function delete($model): void;

    /**
     * Load information about related resources onto a collection of models.
     *
     * @param array $models
     * @param array $relationships
     * @param Closure $scope Should be called to give the deepest relationship
     *   an opportunity to scope the query that will fetch related resources
     * @param bool $linkage true if we just need the IDs of the related
     *   resources and not their full data
     * @return mixed
     */
    public function load(array $models, array $relationships, Closure $scope, bool $linkage): void;

    /**
     * Load information about the IDs of related resources onto a collection
     * of models.
     *
     * @param array $models
     * @param Relationship $relationship
     * @return mixed
     */
    // public function loadIds(array $models, Relationship $relationship): void;
}
