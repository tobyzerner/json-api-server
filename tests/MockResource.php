<?php

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\Pagination\InvalidPageCursorException;
use Tobyz\JsonApiServer\Exception\Pagination\RangePaginationNotSupportedException;
use Tobyz\JsonApiServer\Pagination\Page;
use Tobyz\JsonApiServer\Pagination\Pagination;
use Tobyz\JsonApiServer\Resource\AbstractResource;
use Tobyz\JsonApiServer\Resource\Attachable;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Resource\CursorPaginatable;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Resource\Findable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\Paginatable;
use Tobyz\JsonApiServer\Resource\RelatedListable;
use Tobyz\JsonApiServer\Resource\SupportsBooleanFilters;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Id;

class MockResource extends AbstractResource implements
    Findable,
    Listable,
    Countable,
    Paginatable,
    CursorPaginatable,
    Creatable,
    Updatable,
    Deletable,
    SupportsBooleanFilters,
    RelatedListable,
    Attachable
{
    public function __construct(
        private readonly string $type,
        public array $models = [],
        private readonly array $endpoints = [],
        private readonly ?Id $id = null,
        private readonly array $fields = [],
        private readonly array $meta = [],
        private readonly array $links = [],
        private readonly array $filters = [],
        private readonly array $sorts = [],
        private readonly ?string $defaultSort = null,
        private readonly ?Pagination $pagination = null,
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

    public function id(): Id
    {
        return $this->id ?? parent::id();
    }

    public function fields(): array
    {
        return $this->fields;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function links(): array
    {
        return $this->links;
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function sorts(): array
    {
        return $this->sorts;
    }

    public function defaultSort(): ?string
    {
        return $this->defaultSort;
    }

    public function pagination(): ?Pagination
    {
        return $this->pagination;
    }

    public function query(Context $context): object
    {
        return (object) ['models' => $this->models, 'sorts' => []];
    }

    public function relatedQuery(object $model, ToMany $relationship, Context $context): ?object
    {
        $related = $model->{$relationship->property ?: $relationship->name} ?? [];

        return (object) ['models' => $related, 'sorts' => []];
    }

    public function find(string $id, Context $context): ?object
    {
        foreach ($this->models as $model) {
            if ($model->id == $id) {
                return $model;
            }
        }

        return null;
    }

    public function paginate(object $query, int $offset, int $limit, Context $context): Page
    {
        $query->models = array_slice($query->models, $offset, $limit + 1);

        $results = $this->results($query, $context);

        return new Page(array_slice($results, 0, $limit), $offset === 0, count($results) <= $limit);
    }

    public function cursorPaginate(
        object $query,
        int $size,
        ?string $after,
        ?string $before,
        Context $context,
    ): Page {
        if ($before && $after) {
            throw new RangePaginationNotSupportedException();
        }

        $models = $query->models;

        [$startIndex, $endIndex] = $this->determineBounds($models, $after, $before);

        $range = array_slice($models, $startIndex, max(0, $endIndex - $startIndex));

        if ($before !== null && $after === null) {
            $slice = array_slice($range, max(0, count($range) - $size), $size);
        } else {
            $slice = array_slice($range, 0, $size);
        }

        $rangeTruncated = count($range) > count($slice);

        $slice = array_values($slice);

        return new Page(
            results: $slice,
            isFirstPage: $slice[0] === $models[0],
            isLastPage: !$rangeTruncated,
            rangeTruncated: $rangeTruncated ? true : null,
        );
    }

    public function itemCursor($model, object $query, Context $context): string
    {
        return $model->id;
    }

    private function determineBounds(array $models, ?string $after, ?string $before): array
    {
        $startIndex = 0;

        if ($after !== null) {
            $afterIndex = $this->indexForCursor($models, $after, '[after]');
            $startIndex = $afterIndex + 1;
        }

        $endIndex = count($models);

        if ($before !== null) {
            $endIndex = $this->indexForCursor($models, $before, '[before]');
        }

        return [$startIndex, max($startIndex, $endIndex)];
    }

    private function indexForCursor(array $models, string $cursor, string $parameter): int
    {
        foreach ($models as $index => $model) {
            if (($model->id ?? null) === $cursor) {
                return $index;
            }
        }

        throw (new InvalidPageCursorException())->source(['parameter' => $parameter]);
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

    public function attach(
        object $model,
        Relationship $relationship,
        array $related,
        Context $context,
    ): void {
        $property = $relationship->property ?: $relationship->name;

        $items = $model->{$property} ?? [];

        foreach ($related as $candidate) {
            $already = false;

            foreach ($items as $item) {
                if ($item === $candidate) {
                    $already = true;
                    break;
                }
            }

            if (!$already) {
                $items[] = $candidate;
            }
        }

        $model->{$property} = $items;
    }

    public function detach(
        object $model,
        Relationship $relationship,
        array $related,
        Context $context,
    ): void {
        $property = $relationship->property ?: $relationship->name;

        if (!isset($model->{$property}) || !is_array($model->{$property})) {
            return;
        }

        $ids = array_map(fn($item) => spl_object_id($item), $related);

        $model->{$property} = array_values(
            array_filter(
                $model->{$property},
                fn($item) => !in_array(spl_object_id($item), $ids, true),
            ),
        );
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
