<?php

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\AbstractResource;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Resource\Findable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\Paginatable;
use Tobyz\JsonApiServer\Resource\SupportsBooleanFilters;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Field\Field;

class MockResource extends AbstractResource implements
    Findable,
    Listable,
    Countable,
    Paginatable,
    Creatable,
    Updatable,
    Deletable,
    SupportsBooleanFilters
{
    public function __construct(
        private readonly string $type,
        public array $models = [],
        private readonly array $endpoints = [],
        private readonly array $fields = [],
        private readonly array $meta = [],
        private readonly array $filters = [],
        private readonly array $sorts = [],
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function endpoints(): array
    {
        return $this->endpoints;
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function sorts(): array
    {
        return $this->sorts;
    }

    public function query(Context $context): object
    {
        return (object) ['models' => $this->models, 'sorts' => []];
    }

    public function find(string $id, Context $context): ?object
    {
        foreach ($this->models as $model) {
            if ($model->id === $id) {
                return $model;
            }
        }

        return null;
    }

    public function paginate(object $query, OffsetPagination $pagination): void
    {
        $query->models = array_slice($query->models, $pagination->offset, $pagination->limit);
    }

    public function results(object $query, Context $context): array
    {
        $args = array_merge(
            ...array_map(
                fn($sort) => [
                    array_map(fn($model) => $model->{$sort[0]}, $query->models),
                    $sort[1] === 'desc' ? SORT_DESC : SORT_ASC,
                ],
                $query->sorts,
            ),
        );

        $args[] = &$query->models;

        array_multisort(...$args);

        return $query->models;
    }

    public function count(object $query, Context $context): int
    {
        return count($query->models);
    }

    public function newModel(Context $context): object
    {
        return (object) ['id' => 'created'];
    }

    public function setValue(object $model, Field $field, mixed $value, Context $context): void
    {
        $model->{$field->property ?: $field->name} = $value;
    }

    public function saveValue(object $model, Field $field, mixed $value, Context $context): void
    {
        // noop
    }

    public function create(object $model, Context $context): object
    {
        return $this->models[] = $model;
    }

    public function update(object $model, Context $context): object
    {
        return $model;
    }

    public function delete(object $model, Context $context): void
    {
        $this->models = array_filter($this->models, fn($m) => $m !== $model);
    }

    public function filterOr(object $query, array $clauses): void
    {
        if ($clauses === []) {
            return;
        }

        $originalModels = $query->models;
        $result = [];

        foreach ($clauses as $clause) {
            $branchQuery = clone $query;
            $branchQuery->models = $originalModels;

            $clause($branchQuery);

            foreach ($branchQuery->models as $model) {
                $result[spl_object_id($model)] = $model;
            }
        }

        $query->models = array_values($result);
    }

    public function filterNot(object $query, array $clauses): void
    {
        if ($clauses === []) {
            return;
        }

        $originalModels = $query->models;

        $branchQuery = clone $query;
        $branchQuery->models = $originalModels;

        foreach ($clauses as $clause) {
            $clause($branchQuery);
        }

        $excluded = [];

        foreach ($branchQuery->models as $model) {
            $excluded[spl_object_id($model)] = true;
        }

        $query->models = array_values(
            array_filter($query->models, fn($model) => !isset($excluded[spl_object_id($model)])),
        );
    }
}
