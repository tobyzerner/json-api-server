<?php

namespace Tobscure\JsonApiServer\Handler;

use JsonApiPhp\JsonApi;
use JsonApiPhp\JsonApi\Link;
use JsonApiPhp\JsonApi\Meta;
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

        $request = $this->extractQueryParams($request);

        $adapter = $this->resource->getAdapter();
        $schema = $this->resource->getSchema();

        $query = $adapter->query();

        foreach ($schema->scopes as $scope) {
            $scope($request, $query);
        }

        foreach ($schema->indexScopes as $scope) {
            $scope($request, $query);
        }

        if ($filter = $request->getAttribute('jsonApiFilter')) {
            $this->filter($query, $filter, $request);
        }

        $offset = $request->getAttribute('jsonApiOffset');
        $limit = $request->getAttribute('jsonApiLimit');
        $total = null;

        $paginationLinks = [];
        $members = [
            new Link\SelfLink($this->buildUrl($request))
        ];

        if ($offset > 0) {
            $paginationLinks[] = new Link\FirstLink($this->buildUrl($request, ['page' => ['offset' => 0]]));

            $prevOffset = $offset - $limit;

            if ($prevOffset < 0) {
                $params = ['page' => ['offset' => 0, 'limit' => $offset]];
            } else {
                $params = ['page' => ['offset' => max(0, $prevOffset)]];
            }

            $paginationLinks[] = new Link\PrevLink($this->buildUrl($request, $params));
        }

        if ($schema->countable) {
            $total = $adapter->count($query);

            $members[] = new Meta('total', $total);

            if ($offset + $limit < $total) {
                $paginationLinks[] = new Link\LastLink($this->buildUrl($request, ['page' => ['offset' => floor(($total - 1) / $limit) * $limit]]));
            }
        }

        if ($sort = $request->getAttribute('jsonApiSort')) {
            $this->sort($query, $sort, $request);
        }

        $this->paginate($query, $request);

        $models = $adapter->get($query);

        if ((count($models) && $total === null) || $offset + $limit < $total) {
            $paginationLinks[] = new Link\NextLink($this->buildUrl($request, ['page' => ['offset' => $offset + $limit]]));
        }

        $this->loadRelationships($models, $include, $request);

        $serializer = new Serializer($this->api, $request);

        foreach ($models as $model) {
            $serializer->add($this->resource, $model, $include);
        }

        return new JsonApiResponse(
            new JsonApi\CompoundDocument(
                new JsonApi\PaginatedCollection(
                    new JsonApi\Pagination(...$paginationLinks),
                    new JsonApi\ResourceCollection(...$serializer->primary())
                ),
                new JsonApi\Included(...$serializer->included()),
                ...$members
            )
        );
    }

    private function buildUrl(Request $request, array $overrideParams = []): string
    {
        [$selfUrl] = explode('?', $request->getUri(), 2);
        $queryParams = $request->getQueryParams();

        $queryParams = array_replace_recursive($queryParams, $overrideParams);

        if (isset($queryParams['page']['offset']) && $queryParams['page']['offset'] <= 0) {
            unset($queryParams['page']['offset']);
        }

        $queryString = http_build_query($queryParams);

        return $selfUrl.($queryString ? '?'.$queryString : '');
    }

    private function extractQueryParams(Request $request): Request
    {
        $schema = $this->resource->getSchema();

        $queryParams = $request->getQueryParams();

        $limit = $this->resource->getSchema()->paginate;

        if (isset($queryParams['page']['limit'])) {
            $limit = $queryParams['page']['limit'];

            if ((! is_int($limit) && ! ctype_digit($limit)) || $limit < 1) {
                throw new BadRequestException('page[limit] must be a positive integer', 'page[limit]');
            }

            $limit = min($this->resource->getSchema()->limit, $limit);
        }

        $offset = 0;

        if (isset($queryParams['page']['offset'])) {
            $offset = $queryParams['page']['offset'];

            if ((! is_int($offset) && ! ctype_digit($offset)) || $offset < 0) {
                throw new BadRequestException('page[offset] must be a non-negative integer', 'page[offset]');
            }
        }

        $request = $request
            ->withAttribute('jsonApiLimit', $limit)
            ->withAttribute('jsonApiOffset', $offset);

        $sort = $queryParams['sort'] ?? $this->resource->getSchema()->defaultSort;

        if ($sort) {
            $sort = $this->parseSort($sort);

            foreach ($sort as $name => $direction) {
                if (! isset($schema->fields[$name])
                    || ! $schema->fields[$name] instanceof Schema\Attribute
                    || ! $schema->fields[$name]->sortable
                ) {
                    throw new BadRequestException("Invalid sort field [$name]", 'sort');
                }
            }
        }

        $request = $request->withAttribute('jsonApiSort', $sort);

        $filter = $queryParams['filter'] ?? null;

        if ($filter) {
            if (! is_array($filter)) {
                throw new BadRequestException('filter must be an array', 'filter');
            }

            foreach ($filter as $name => $value) {
                if (! isset($schema->fields[$name])
                    || ! $schema->fields[$name]->filterable
                ) {
                    throw new BadRequestException("Invalid filter [$name]", "filter[$name]");
                }
            }
        }

        $request = $request->withAttribute('jsonApiFilter', $filter);

        return $request;
    }

    private function sort($query, array $sort, Request $request)
    {
        $schema = $this->resource->getSchema();
        $adapter = $this->resource->getAdapter();

        foreach ($sort as $name => $direction) {
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
        $limit = $request->getAttribute('jsonApiLimit');
        $offset = $request->getAttribute('jsonApiOffset');

        if ($limit || $offset) {
            $this->resource->getAdapter()->paginate($query, $limit, $offset);
        }
    }

    private function filter($query, $filter, Request $request)
    {
        $schema = $this->resource->getSchema();
        $adapter = $this->resource->getAdapter();

        foreach ($filter as $name => $value) {
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
}
