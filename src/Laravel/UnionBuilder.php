<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;

class UnionBuilder implements Builder
{
    private array $outerQueryCalls = [];
    private ?int $limit = null;
    private int $offset = 0;

    public function __construct(protected array $queries)
    {
    }

    public function for(string $type): Builder
    {
        return $this->queries[$type];
    }

    public function inner(callable $callback): static
    {
        foreach ($this->queries as $query) {
            $callback($query);
        }

        return $this;
    }

    public function outer(callable $callback): static
    {
        $this->outerQueryCalls[] = $callback;

        return $this;
    }

    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    public function offset(int $value): static
    {
        $this->offset = max(0, $value);

        return $this;
    }

    public function take(?int $value): static
    {
        return $this->limit($value);
    }

    public function limit(?int $value): static
    {
        if ($value >= 0) {
            $this->limit = $value;
        }

        return $this;
    }

    public function count($columns = '*'): int
    {
        return $this->buildQuery()->count($columns);
    }

    public function get($columns = ['*']): Collection
    {
        return $this->buildQuery()->get($columns);
    }

    protected function buildQuery(): Builder
    {
        $queries = array_map(fn($query) => clone $query, $this->queries);

        foreach ($queries as $query) {
            if ($this->limit) {
                $query->take($this->offset + $this->limit);
            }
        }

        $outerQuery = array_shift($queries);

        foreach ($queries as $query) {
            $outerQuery->union($query);
        }

        foreach ($this->outerQueryCalls as $call) {
            $call($outerQuery);
        }

        if ($this->limit) {
            $outerQuery->skip($this->offset)->take($this->limit);
        }

        return $outerQuery;
    }

    public function __call($method, $parameters)
    {
        $callback = fn($query) => $query->$method(...$parameters);

        $this->inner($callback);
        $this->outer($callback);

        return $this;
    }
}
