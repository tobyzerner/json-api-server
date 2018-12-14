<?php

namespace Tobscure\JsonApiServer\Handler;

use JsonApiPhp\JsonApi;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobscure\JsonApiServer\Api;
use Tobscure\JsonApiServer\Exception\BadRequestException;
use Tobscure\JsonApiServer\JsonApiResponse;
use Tobscure\JsonApiServer\ResourceType;
use Tobscure\JsonApiServer\Schema;
use Tobscure\JsonApiServer\Serializer;

class Index implements RequestHandlerInterface
{
    use Concerns\IncludesData;

    private $api;
    private $resource;

    public function __construct(Api $api, ResourceType $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
    }

    public function handle(Request $request): Response
    {
        $include = $this->getInclude($request);

        $models = $this->getModels($include, $request);

        $serializer = new Serializer($this->api, $request);

        foreach ($models as $model) {
            $serializer->add($this->resource, $model, $include);
        }

        return new JsonApiResponse(
            new JsonApi\CompoundDocument(
                new JsonApi\ResourceCollection(...$serializer->primary()),
                new JsonApi\Included(...$serializer->included())
            )
        );
    }

    private function getModels(array $include, Request $request)
    {
        $adapter = $this->resource->getAdapter();

        $query = $adapter->query();

        foreach ($this->resource->getSchema()->scopes as $scope) {
            $scope($query, $request);
        }

        $queryParams = $request->getQueryParams();

        if (isset($queryParams['sort'])) {
            $this->sort($query, $queryParams['sort'], $request);
        }

        if (isset($queryParams['filter'])) {
            $this->filter($query, $queryParams['filter'], $request);
        }

        $this->paginate($query, $request);

        $this->include($query, $include);

        return $adapter->get($query);
    }

    private function sort($query, string $sort, Request $request)
    {
        $schema = $this->resource->getSchema();
        $adapter = $this->resource->getAdapter();

        foreach ($this->parseSort($sort) as $name => $direction) {
            if (! isset($schema->fields[$name])
                || ! $schema->fields[$name] instanceof Schema\Attribute
                || ! $schema->fields[$name]->sortable
            ) {
                throw new BadRequestException("Invalid sort field [$name]");
            }

            $attribute = $schema->fields[$name];

            if ($attribute->sorter) {
                ($attribute->sorter)($query, $direction, $request);
            } else {
                $adapter->sortByAttribute($query, $attribute, $direction);
            }
        }
    }

    private function parseSort(string $string): array
    {
        $sort = [];
        $fields = explode(',', $string);

        foreach ($fields as $field) {
            if (substr($field, 0, 1) === '-') {
                $field = substr($field, 1);
                $direction = 'desc';
            } else {
                $direction = 'asc';
            }

            $sort[$field] = $direction;
        }

        return $sort;
    }

    private function paginate($query, Request $request)
    {
        $queryParams = $request->getQueryParams();

        $maxLimit = $this->resource->getSchema()->paginate;

        $limit = isset($queryParams['page']['limit']) ? min($maxLimit, (int) $queryParams['page']['limit']) : $maxLimit;

        $offset = isset($queryParams['page']['offset']) ? (int) $queryParams['page']['offset'] : 0;

        if ($offset < 0) {
            throw new BadRequestException('page[offset] must be >=0');
        }

        if ($limit) {
            $this->resource->getAdapter()->paginate($query, $limit, $offset);
        }
    }

    private function filter($query, $filter, Request $request)
    {
        $schema = $this->resource->getSchema();
        $adapter = $this->resource->getAdapter();

        if (! is_array($filter)) {
            throw new BadRequestException('filter must be an array');
        }

        foreach ($filter as $name => $value) {
            if (! isset($schema->fields[$name])
                || ! $schema->fields[$name]->filterable
            ) {
                throw new BadRequestException("Invalid filter [$name]");
            }

            $field = $schema->fields[$name];

            if ($field->filter) {
                ($field->filter)($query, $value, $request);
            } elseif ($field instanceof Schema\Attribute) {
                $adapter->filterByAttribute($query, $field, $value);
            } elseif ($field instanceof Schema\HasOne) {
                $value = explode(',', $value);
                $adapter->filterByHasOne($query, $field, $value);
            } elseif ($field instanceof Schema\HasMany) {
                $value = explode(',', $value);
                $adapter->filterByHasMany($query, $field, $value);
            }
        }
    }

    private function include($query, array $include)
    {
        $adapter = $this->resource->getAdapter();

        $trails = $this->buildRelationshipTrails($this->resource, $include);

        foreach ($trails as $relationships) {
            $adapter->include($query, $relationships);
        }
    }
}
