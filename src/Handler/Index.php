<?php

namespace Tobyz\JsonApiServer\Handler;

use Closure;
use JsonApiPhp\JsonApi as Structure;
use JsonApiPhp\JsonApi\Link\LastLink;
use JsonApiPhp\JsonApi\Link\NextLink;
use JsonApiPhp\JsonApi\Link\PrevLink;
use JsonApiPhp\JsonApi\Meta;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApiResponse;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\JsonApiServer\Serializer;

class Index implements RequestHandlerInterface
{
    use Concerns\IncludesData;

    private $api;
    private $resource;

    public function __construct(JsonApi $api, ResourceType $resource)
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

        foreach ($schema->getScopes() as $scope) {
            $request = $scope($request, $query) ?: $request;
        }

        if ($filter = $request->getAttribute('jsonApiFilter')) {
            $this->filter($query, $filter, $request);
        }

        $offset = $request->getAttribute('jsonApiOffset');
        $limit = $request->getAttribute('jsonApiLimit');
        $total = null;

        $paginationLinks = [];
        $members = [
            new Structure\Link\SelfLink($this->buildUrl($request)),
            new Structure\Meta('offset', $offset),
            new Structure\Meta('limit', $limit),
        ];

        if ($offset > 0) {
            $paginationLinks[] = new Structure\Link\FirstLink($this->buildUrl($request, ['page' => ['offset' => 0]]));

            $prevOffset = $offset - $limit;

            if ($prevOffset < 0) {
                $params = ['page' => ['offset' => 0, 'limit' => $offset]];
            } else {
                $params = ['page' => ['offset' => max(0, $prevOffset)]];
            }

            $paginationLinks[] = new PrevLink($this->buildUrl($request, $params));
        }

        if ($schema->isCountable() && $schema->getPaginate()) {
            $total = $adapter->count($query);

            $members[] = new Meta('total', $total);

            if ($offset + $limit < $total) {
                $paginationLinks[] = new LastLink($this->buildUrl($request, ['page' => ['offset' => floor(($total - 1) / $limit) * $limit]]));
            }
        }

        if ($sort = $request->getAttribute('jsonApiSort')) {
            $this->sort($query, $sort, $request);
        }

        $this->paginate($query, $request);

        $models = $adapter->get($query);

        if ((count($models) && $total === null) || $offset + $limit < $total) {
            $paginationLinks[] = new NextLink($this->buildUrl($request, ['page' => ['offset' => $offset + $limit]]));
        }

        $this->loadRelationships($models, $include, $request);

        $serializer = new Serializer($this->api, $request);

        foreach ($models as $model) {
            $serializer->add($this->resource, $model, $include);
        }

        return new JsonApiResponse(
            new Structure\CompoundDocument(
                new Structure\PaginatedCollection(
                    new Structure\Pagination(...$paginationLinks),
                    new Structure\ResourceCollection(...$serializer->primary())
                ),
                new Structure\Included(...$serializer->included()),
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

        if (isset($queryParams['filter'])) {
            foreach ($queryParams['filter'] as $k => &$v) {
                if ($v === null) {
                    $v = '';
                }
            }
        }

        $queryString = http_build_query($queryParams);

        return $selfUrl.($queryString ? '?'.$queryString : '');
    }

    private function extractQueryParams(Request $request): Request
    {
        $schema = $this->resource->getSchema();

        $queryParams = $request->getQueryParams();

        $limit = $this->resource->getSchema()->getPaginate();

        if (isset($queryParams['page']['limit'])) {
            $limit = $queryParams['page']['limit'];

            if ((! is_int($limit) && ! ctype_digit($limit)) || $limit < 1) {
                throw new BadRequestException('page[limit] must be a positive integer', 'page[limit]');
            }

            $limit = min($this->resource->getSchema()->getLimit(), $limit);
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

        $sort = $queryParams['sort'] ?? $this->resource->getSchema()->getDefaultSort();

        if ($sort) {
            $sort = $this->parseSort($sort);
            $fields = $schema->getFields();

            foreach ($sort as $name => $direction) {
                if (! isset($fields[$name])
                    || ! $fields[$name] instanceof Attribute
                    || ! $fields[$name]->getSortable()
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

            $fields = $schema->getFields();

            foreach ($filter as $name => $value) {
                if ($name !== 'id' && (! isset($fields[$name]) || ! $fields[$name]->getFilterable())) {
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
            $attribute = $schema->getFields()[$name];

            if (($sorter = $attribute->getSortable()) instanceof Closure) {
                $sorter($query, $direction, $request);
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
            if ($name === 'id') {
                $adapter->filterByIds($query, explode(',', $value));
                continue;
            }

            $field = $schema->getFields()[$name];

            if (($filter = $field->getFilterable()) instanceof Closure) {
                $filter($query, $value, $request);
            } elseif ($field instanceof Attribute) {
                $adapter->filterByAttribute($query, $field, $value);
            } elseif ($field instanceof HasOne) {
                $value = explode(',', $value);
                $adapter->filterByHasOne($query, $field, $value);
            } elseif ($field instanceof HasMany) {
                $value = explode(',', $value);
                $adapter->filterByHasMany($query, $field, $value);
            }
        }
    }
}
