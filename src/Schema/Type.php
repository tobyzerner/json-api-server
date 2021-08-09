<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasListeners;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use function Tobyz\JsonApiServer\negate;

final class Type
{
    use HasListeners;
    use HasMeta;
    use HasDescription;

    private $fields = [];
    private $filters = [];
    private $sorts = [];
    private $perPage = 20;
    private $limit = 50;
    private $countable = true;
    private $listable = true;
    private $defaultSort;
    private $saveCallback;
    private $newModelCallback;
    private $creatable = false;
    private $updatable = false;
    private $deletable = false;
    private $deleteCallback;

    /**
     * Add an attribute to the resource type.
     *
     * If an attribute has already been defined with this name, it will be
     * returned. Otherwise, the field will be overwritten.
     */
    public function attribute(string $name): Attribute
    {
        return $this->field(Attribute::class, $name);
    }

    /**
     * Add a has-one relationship to the resource type.
     *
     * If a has-one relationship has already been defined with this name, it
     * will be returned. Otherwise, the field will be overwritten.
     */
    public function hasOne(string $name): HasOne
    {
        return $this->field(HasOne::class, $name);
    }

    /**
     * Add a has-many relationship to the resource type.
     *
     * If a has-many relationship has already been defined with this name, it
     * will be returned. Otherwise, the field will be overwritten.
     */
    public function hasMany(string $name): HasMany
    {
        return $this->field(HasMany::class, $name);
    }

    private function field(string $class, string $name)
    {
        if (! isset($this->fields[$name]) || ! $this->fields[$name] instanceof $class) {
            $this->fields[$name] = new $class($name);
        }

        return $this->fields[$name];
    }

    /**
     * Remove a field from the resource type.
     */
    public function removeField(string $name): void
    {
        unset($this->fields[$name]);
    }

    /**
     * Get the resource type's fields.
     *
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Add a filter to the resource type.
     */
    public function filter(string $name, callable $callback): Filter
    {
        return $this->filters[$name] = new Filter($name, $callback);
    }

    /**
     * Get the resource type's filters.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Add a sort field to the resource type.
     */
    public function sort(string $name, callable $callback): void
    {
        $this->sorts[$name] = new Sort($name, $callback);
    }

    /**
     * Get the resource type's sort fields.
     */
    public function getSorts(): array
    {
        return $this->sorts;
    }

    /**
     * Paginate the listing of the resource type.
     */
    public function paginate(int $perPage): void
    {
        $this->perPage = $perPage;
    }

    /**
     * Don't paginate the listing of the resource type.
     */
    public function dontPaginate(): void
    {
        $this->perPage = null;
    }

    /**
     * Get the number of records to list per page, or null if the list should
     * not be paginated.
     */
    public function getPerPage(): ?int
    {
        return $this->perPage;
    }

    /**
     * Limit the maximum number of records that can be listed.
     */
    public function limit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * Allow unlimited records to be listed.
     */
    public function noLimit(): void
    {
        $this->limit = null;
    }

    /**
     * Get the maximum number of records that can be listed, or null if there
     * is no limit.
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Mark the resource type as countable.
     */
    public function countable(): void
    {
        $this->countable = true;
    }

    /**
     * Mark the resource type as uncountable.
     */
    public function uncountable(): void
    {
        $this->countable = false;
    }

    /**
     * Get whether or not the resource type is countable.
     */
    public function isCountable(): bool
    {
        return $this->countable;
    }

    /**
     * Apply a scope to the query to fetch record(s).
     */
    public function scope(callable $callback): void
    {
        $this->listeners['scope'][] = $callback;
    }

    /**
     * Run a callback before a resource is shown.
     */
    public function show(callable $callback): void
    {
        $this->listeners['show'][] = $callback;
    }

    /**
     * Allow the resource type to be listed.
     */
    public function listable(callable $condition = null): void
    {
        $this->listable = $condition ?: true;
    }

    /**
     * Disallow the resource type to be listed.
     */
    public function notListable(callable $condition = null): void
    {
        $this->listable = $condition ? negate($condition) : false;
    }

    /**
     * Get whether or not the resource type is allowed to be listed.
     */
    public function isListable()
    {
        return $this->listable;
    }

    /**
     * Run a callback before the resource type is listed.
     */
    public function listing(callable $callback): void
    {
        $this->listeners['listing'][] = $callback;
    }

    /**
     * Run a callback when the resource type is listed.
     */
    public function listed(callable $callback): void
    {
        $this->listeners['listed'][] = $callback;
    }

    /**
     * Set the callback to create a new model instance.
     *
     * If null, the adapter will be used to create new model instances.
     */
    public function newModel(?callable $callback): void
    {
        $this->newModelCallback = $callback;
    }

    /**
     * Get the callback to create a new model instance.
     */
    public function getNewModelCallback(): ?callable
    {
        return $this->newModelCallback;
    }

    /**
     * Allow the resource type to be created.
     */
    public function creatable(callable $condition = null): void
    {
        $this->creatable = $condition ?: true;
    }

    /**
     * Disallow the resource type to be created.
     */
    public function notCreatable(callable $condition = null): void
    {
        $this->creatable = $condition ? negate($condition) : false;
    }

    /**
     * Get whether or not the resource type is allowed to be created.
     */
    public function isCreatable()
    {
        return $this->creatable;
    }

    /**
     * Run a callback before a resource is created.
     */
    public function creating(callable $callback): void
    {
        $this->listeners['creating'][] = $callback;
    }

    /**
     * Run a callback after a resource has been created.
     */
    public function created(callable $callback): void
    {
        $this->listeners['created'][] = $callback;
    }

    /**
     * Allow the resource type to be updated.
     */
    public function updatable(callable $condition = null): void
    {
        $this->updatable = $condition ?: true;
    }

    /**
     * Disallow the resource type to be updated.
     */
    public function notUpdatable(callable $condition = null): void
    {
        $this->updatable = $condition ? negate($condition) : false;
    }

    /**
     * Get whether or not the resource type is allowed to be updated.
     */
    public function isUpdatable()
    {
        return $this->updatable;
    }

    /**
     * Run a callback before a resource has been updated.
     */
    public function updating(callable $callback): void
    {
        $this->listeners['updating'][] = $callback;
    }

    /**
     * Run a callback after a resource has been updated.
     */
    public function updated(callable $callback): void
    {
        $this->listeners['updated'][] = $callback;
    }

    /**
     * Set the callback to save a model instance.
     *
     * If null, the adapter will be used to save model instances.
     */
    public function save(?callable $callback): void
    {
        $this->saveCallback = $callback;
    }

    /**
     * Get the callback to save a model instance.
     */
    public function getSaveCallback(): ?callable
    {
        return $this->saveCallback;
    }

    /**
     * Allow the resource type to be deleted.
     */
    public function deletable(callable $condition = null): void
    {
        $this->deletable = $condition ?: true;
    }

    /**
     * Disallow the resource type to be deleted.
     */
    public function notDeletable(callable $condition = null): void
    {
        $this->deletable = $condition ? negate($condition) : false;
    }

    /**
     * Get whether or not the resource type is allowed to be deleted.
     */
    public function isDeletable()
    {
        return $this->deletable;
    }

    /**
     * Set the callback to delete a model instance.
     *
     * If null, the adapter will be used to delete model instances.
     */
    public function delete(?callable $callback): void
    {
        $this->deleteCallback = $callback;
    }

    /**
     * Get the callback to delete a model instance.
     */
    public function getDeleteCallback(): ?callable
    {
        return $this->deleteCallback;
    }

    /**
     * Run a callback before a resource has been deleted.
     */
    public function deleting(callable $callback): void
    {
        $this->listeners['deleting'][] = $callback;
    }

    /**
     * Run a callback after a resource has been deleted.
     */
    public function deleted(callable $callback): void
    {
        $this->listeners['deleted'][] = $callback;
    }

    /**
     * Set the default sort parameter value to be used if none is specified in
     * the query string.
     */
    public function defaultSort(?string $sort): void
    {
        $this->defaultSort = $sort;
    }

    /**
     * Get the default sort parameter value to be used if none is specified in
     * the query string.
     */
    public function getDefaultSort(): ?string
    {
        return $this->defaultSort;
    }
}
