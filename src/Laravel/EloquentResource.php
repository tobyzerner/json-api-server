<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\AbstractResource;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Resource\Findable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\Paginatable;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Type\DateTime;

abstract class EloquentResource extends AbstractResource implements
    Findable,
    Listable,
    Countable,
    Paginatable,
    Creatable,
    Updatable,
    Deletable
{
    public function resource(object $model, Context $context): ?string
    {
        $eloquentModel = $this->newModel($context);

        if ($model instanceof $eloquentModel) {
            return $this->type();
        }

        return null;
    }

    public function getId(object $model, Context $context): string
    {
        return $model->getKey();
    }

    public function getValue(object $model, Field $field, Context $context): mixed
    {
        if ($field instanceof Relationship) {
            return $this->getRelationshipValue($model, $field, $context);
        } else {
            return $this->getAttributeValue($model, $field, $context);
        }
    }

    protected function getAttributeValue(Model $model, Field $field, Context $context)
    {
        return $model->getAttribute($this->property($field));
    }

    protected function getRelationshipValue(Model $model, Relationship $field, Context $context)
    {
        $method = $this->method($field);

        if ($model->isRelation($method)) {
            $relation = $model->$method();

            // If this is a belongs-to relationship, and we only need to get the ID
            // for linkage, then we don't have to actually load the relation because
            // the ID is stored in a column directly on the model. We will mock up a
            // related model with the value of the ID filled.
            if ($relation instanceof BelongsTo && $context->include === null) {
                if ($key = $model->getAttribute($relation->getForeignKeyName())) {
                    if ($relation instanceof MorphTo) {
                        $morphType = $model->{$relation->getMorphType()};
                        $related = $relation->createModelByType($morphType);
                    } else {
                        $related = $relation->getRelated();
                    }

                    return $related->newInstance()->forceFill([$related->getKeyName() => $key]);
                }

                return null;
            }

            EloquentBuffer::add($model, $method);

            return function () use ($model, $method, $field, $context) {
                EloquentBuffer::load($model, $method, $field, $context);

                $data = $model->getRelation($method);

                return $data instanceof Collection ? $data->all() : $data;
            };
        }

        return $this->getAttributeValue($model, $field, $context);
    }

    public function query(Context $context): object
    {
        $query = $this->newModel($context)->query();

        $this->scope($query, $context);

        return $query;
    }

    /**
     * Hook to scope a query for this resource.
     */
    public function scope(Builder $query, Context $context): void
    {
    }

    public function results(object $query, Context $context): array
    {
        return $query->get()->all();
    }

    public function paginate(object $query, OffsetPagination $pagination): void
    {
        $query->take($pagination->limit)->skip($pagination->offset);
    }

    public function count(object $query, Context $context): ?int
    {
        return $query->toBase()->getCountForPagination();
    }

    public function find(string $id, Context $context): ?object
    {
        return $this->query($context)->find($id);
    }

    public function setValue(object $model, Field $field, mixed $value, Context $context): void
    {
        if ($field instanceof Relationship) {
            $method = $this->method($field);
            $relation = $model->$method();

            // If this is a belongs-to relationship, then the ID is stored on the
            // model itself, so we can set it here.
            if ($relation instanceof BelongsTo) {
                $relation->associate($value);
            }

            return;
        }

        // Mind-blowingly, Laravel discards timezone information when storing
        // dates in the database. Since the API can receive dates in any
        // timezone, we will need to convert it to the app's configured
        // timezone ourselves before storage.
        if (
            $field instanceof Attribute &&
            $field->type instanceof DateTime &&
            $value instanceof \DateTimeInterface
        ) {
            $value = \DateTime::createFromInterface($value)->setTimezone(
                new \DateTimeZone(config('app.timezone')),
            );
        }

        $model->setAttribute($this->property($field), $value);
    }

    public function saveValue(object $model, Field $field, mixed $value, Context $context): void
    {
        if ($field instanceof ToMany) {
            $method = $this->method($field);
            $relation = $model->$method();

            if ($relation instanceof BelongsToMany) {
                $relation->sync(new Collection($value));
            }
        }
    }

    public function create(object $model, Context $context): object
    {
        if (method_exists($this, 'creating')) {
            $model = $this->creating($model, $context) ?: $model;
        }

        if (method_exists($this, 'saving')) {
            $model = $this->saving($model, $context) ?: $model;
        }

        $this->saveModel($model, $context);

        if (method_exists($this, 'saved')) {
            $model = $this->saved($model, $context) ?: $model;
        }

        if (method_exists($this, 'created')) {
            $model = $this->created($model, $context) ?: $model;
        }

        return $model;
    }

    public function update(object $model, Context $context): object
    {
        if (method_exists($this, 'updating')) {
            $model = $this->updating($model, $context) ?: $model;
        }

        if (method_exists($this, 'saving')) {
            $model = $this->saving($model, $context) ?: $model;
        }

        $this->saveModel($model, $context);

        if (method_exists($this, 'saved')) {
            $model = $this->saved($model, $context) ?: $model;
        }

        if (method_exists($this, 'updated')) {
            $model = $this->updated($model, $context) ?: $model;
        }

        return $model;
    }

    protected function saveModel(Model $model, Context $context): void
    {
        $model->save();
    }

    public function delete(object $model, Context $context): void
    {
        $model->delete();
    }

    /**
     * Get the model property that a field represents.
     */
    protected function property(Field $field): string
    {
        return $field->property ?: Str::snake($field->name);
    }

    /**
     * Get the model method that a field represents.
     */
    protected function method(Field $field): string
    {
        return $field->property ?: $field->name;
    }
}
