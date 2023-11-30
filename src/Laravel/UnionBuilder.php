<?php

namespace Tobyz\JsonApiServer\Laravel;

use Illuminate\Contracts\Database\Query\Builder;

class UnionBuilder implements Builder
{
    private array $queryCalls = [];
    private array $outerQueryCalls = [];

    public function __construct(protected array $queries)
    {
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
        }

        $outerQuery = array_shift($queries);

        foreach ($queries as $query) {
            $outerQuery->union($query);
        }

        foreach ($this->outerQueryCalls as $call) {
            $call($outerQuery);
        }

        return $outerQuery;
    }

    public function __call($method, $parameters)
    {
        $call = $this->queryCalls[] = fn($query) => $query->$method(...$parameters);

        if ($method === 'orderBy') {
            $this->queryCalls[] = fn($query) => $query->addSelect($parameters[0]);
        }

        if (in_array($method, ['take', 'limit', 'skip', 'offset', 'orderBy'])) {
            $this->outerQueryCalls[] = $call;
        }

        return $this;
    }
}
