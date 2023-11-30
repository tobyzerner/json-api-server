<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Contracts\Database\Query\Builder;

class UnionBuilder implements Builder
{
    private array $queryCalls = [];
    private array $outerQueryCalls = [];
    private ?int $limit = null;
    private int $offset = 0;

    public function __construct(protected array $queries)
    {
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

    public function orderBy($column, $direction = 'asc'): static
    {
        $this->queryCalls[] = fn($query) => $query
            ->addSelect($column)
            ->orderBy($column, $direction);

        $this->outerQueryCalls[] = fn($query) => $query->orderBy($column, $direction);

        return $this;
    }

    public function count($columns = '*'): int
    {
        return $this->buildQuery()->count($columns);
    }

    public function get($columns = ['*'])
    {
        return $this->buildQuery()->get($columns);
    }

    protected function buildQuery()
    {
        $queries = array_map(fn($query) => clone $query, $this->queries);

        foreach ($queries as $query) {
            foreach ($this->queryCalls as $call) {
                $call($query);
            }

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
        $this->queryCalls[] = fn($query) => $query->$method(...$parameters);

        return $this;
    }
}
