<?php

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\Paginatable;

class MockCollection implements Collection, Listable, Paginatable
{
    public function __construct(
        private readonly string $name,
        public array $models = [],
        private readonly array $endpoints = [],
        private readonly array $filters = [],
        private readonly array $sorts = [],
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function resources(): array
    {
        return array_keys($this->models);
    }

    public function resource(object $model, Context $context): ?string
    {
        foreach ($this->models as $resource => $models) {
            if (in_array($model, $models, true)) {
                return $resource;
            }
        }

        return null;
    }

    public function endpoints(): array
    {
        return $this->endpoints;
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
        return (object) [
            'models' => array_merge(...array_values($this->models)),
            'sorts' => [],
        ];
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
}
