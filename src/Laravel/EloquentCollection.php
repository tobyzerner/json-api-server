<?php

namespace Tobyz\JsonApiServer\Laravel;

use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Pagination\Page;
use Tobyz\JsonApiServer\Resource\AbstractCollection;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\Paginatable;

abstract class EloquentCollection extends AbstractCollection implements
    Listable,
    Countable,
    Paginatable
{
    public function resource(object $model, Context $context): ?string
    {
        foreach ($this->eloquentResources($context) as $resource) {
            $class = $resource->newModel($context);

            if ($model instanceof $class) {
                return $resource->type();
            }
        }

        return null;
    }

    /**
     * @return EloquentResource[]
     */
    public function eloquentResources(Context $context): array
    {
        return array_map(function ($type) use ($context) {
            $resource = $context->resource($type);

            if (!$resource instanceof EloquentResource) {
                throw new RuntimeException('must be Eloquent resource');
            }

            return $resource;
        }, $this->resources());
    }

    public function query(Context $context): object
    {
        $queries = [];

        foreach ($this->eloquentResources($context) as $resource) {
            $keyName = $resource->newModel($context)->getQualifiedKeyName();
            $type = $resource->type();

            $queries[$type] = $resource
                ->query($context)
                ->select("$keyName as id")
                ->selectRaw('? as type', [$type]);
        }

        $query = new UnionBuilder($queries);

        $this->scope($query, $context);

        return $query;
    }

    public function scope(UnionBuilder $query, Context $context): void
    {
    }

    public function results(object $query, Context $context): array
    {
        $results = $query->get();
        $types = $results->groupBy('type');

        foreach ($types as $type => $rows) {
            $model = $context->resource($type)->newModel($context);

            $types[$type] = $model::findMany($rows->pluck('id'));
        }

        return $results->map(fn($row) => $types[$row->type]->find($row->id))->all();
    }

    public function paginate(object $query, int $offset, int $limit, Context $context): Page
    {
        $results = $this->results($query->take($limit + 1)->skip($offset), $context);

        return new Page(array_slice($results, 0, $limit), isLastPage: count($results) <= $limit);
    }

    public function count(object $query, Context $context): ?int
    {
        return $query->count();
    }
}
