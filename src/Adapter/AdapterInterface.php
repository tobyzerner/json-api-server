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
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Deferred;
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
     */
    public function query();

    /**
     * Manipulate the query to only include resources with the given IDs.
     */
    public function filterByIds($query, array $ids): void;

    /**
     * Manipulate the query to only include resources with a certain attribute
     * value.
     *
     * @param string $operator The operator to use for comparison: = < > <= >=
     */
    public function filterByAttribute($query, Attribute $attribute, $value, string $operator = '='): void;

    /**
     * Manipulate the query to only include resources with a relationship within
     * the given scope.
     */
    public function filterByRelationship($query, Relationship $relationship, Closure $scope): void;

    /**
     * Manipulate the query to only include resources appropriate to given filter expression.
     *
     * @param string $expression The filter expression
     */
    public function filterByExpression($query, string $expression): void;

    /**
     * Manipulate the query to sort by the given attribute in the given direction.
     */
    public function sortByAttribute($query, Attribute $attribute, string $direction): void;

    /**
     * Manipulate the query to only include a certain number of results,
     * starting from the given offset.
     */
    public function paginate($query, int $limit, int $offset): void;

    /**
     * Find a single resource by ID from the query.
     */
    public function find($query, string $id);

    /**
     * Get a list of resources from the query.
     */
    public function get($query): array;

    /**
     * Get the number of results from the query.
     */
    public function count($query): int;

    /**
     * Get the ID from the model.
     */
    public function getId($model): string;

    /**
     * Get the value of an attribute from the model.
     *
     * @return mixed|Deferred
     */
    public function getAttribute($model, Attribute $attribute);

    /**
     * Get the model for a has-one relationship for the model.
     *
     * @return mixed|null|Deferred
     */
    public function getHasOne($model, HasOne $relationship, bool $linkageOnly, Context $context);

    /**
     * Get a list of models for a has-many relationship for the model.
     *
     * @return array|Deferred
     */
    public function getHasMany($model, HasMany $relationship, bool $linkageOnly, Context $context);

    /**
     * Determine whether this resource type represents the given model.
     *
     * This is used for polymorphic relationships, where there are one or many
     * related models of unknown type. The first resource type with an adapter
     * that responds positively from this method will be used.
     */
    public function represents($model): bool;

    /**
     * Create a new model instance.
     */
    public function model();

    /**
     * Apply a user-generated ID to the model.
     */
    public function setId($model, string $id): void;

    /**
     * Apply an attribute value to the model.
     */
    public function setAttribute($model, Attribute $attribute, $value): void;

    /**
     * Apply a has-one relationship value to the model.
     */
    public function setHasOne($model, HasOne $relationship, $related): void;

    /**
     * Save the model.
     */
    public function save($model): void;

    /**
     * Save a has-many relationship for the model.
     */
    public function saveHasMany($model, HasMany $relationship, array $related): void;

    /**
     * Delete the model.
     */
    public function delete($model): void;
}
