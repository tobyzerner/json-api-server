<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer\Endpoint;

use JsonApiPhp\JsonApi as Structure;
use JsonApiPhp\JsonApi\Link\LastLink;
use JsonApiPhp\JsonApi\Link\NextLink;
use JsonApiPhp\JsonApi\Link\PrevLink;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Serializer;
use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\run_callbacks;

class Index
{
    use Concerns\IncludesData;

    private $api;
    private $resource;

    public function __construct(JsonApi $api, ResourceType $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
    }

    /**
     * Handle a request to show a resource listing.
     */
    public function handle(Context $context): ResponseInterface
    {
        $adapter = $this->resource->getAdapter();
        $schema = $this->resource->getSchema();

        if (! evaluate($schema->isListable(), [$context])) {
            throw new ForbiddenException;
        }

        $query = $adapter->newQuery();

        run_callbacks($schema->getListeners('listing'), [$query, $context]);
        run_callbacks($schema->getListeners('scope'), [$query, $context]);

        $include = $this->getInclude($context);

        [$offset, $limit] = $this->paginate($query, $context);
        $this->sort($query, $context);
        $this->filter($query, $context);

        $total = $schema->isCountable() ? $adapter->count($query) : null;
        $models = $adapter->get($query);

        $this->loadRelationships($models, $include, $context);

        run_callbacks($schema->getListeners('listed'), [$models, $context]);

        $serializer = new Serializer($this->api, $context);

        foreach ($models as $model) {
            $serializer->add($this->resource, $model, $include);
        }
        
        return json_api_response(
            new Structure\CompoundDocument(
                new Structure\PaginatedCollection(
                    new Structure\Pagination(...$this->buildPaginationLinks($context->getRequest(), $offset, $limit, count($models), $total)),
                    new Structure\ResourceCollection(...$serializer->primary())
                ),
                new Structure\Included(...$serializer->included()),
                new Structure\Link\SelfLink($this->buildUrl($context->getRequest())),
                new Structure\Meta('offset', $offset),
                new Structure\Meta('limit', $limit),
                ...($total !== null ? [new Structure\Meta('total', $total)] : [])
            )
        );
    }

    private function buildUrl(Request $request, array $overrideParams = []): string
    {
        [$selfUrl] = explode('?', $request->getUri(), 2);

        $queryParams = array_replace_recursive($request->getQueryParams(), $overrideParams);

        if (isset($queryParams['page']['offset']) && $queryParams['page']['offset'] <= 0) {
            unset($queryParams['page']['offset']);
        }

        if (isset($queryParams['filter'])) {
            foreach ($queryParams['filter'] as $k => &$v) {
                $v = $v === null ? '' : $v;
            }
        }

        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        return $selfUrl.($queryString ? '?'.$queryString : '');
    }

    private function buildPaginationLinks(Request $request, int $offset, ?int $limit, int $count, ?int $total)
    {
        $paginationLinks = [];
        $schema = $this->resource->getSchema();

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

        if ($schema->isCountable() && $schema->getPerPage() && $offset + $limit < $total) {
            $paginationLinks[] = new LastLink($this->buildUrl($request, ['page' => ['offset' => floor(($total - 1) / $limit) * $limit]]));
        }

        if (($total === null && $count === $limit) || $offset + $limit < $total) {
            $paginationLinks[] = new NextLink($this->buildUrl($request, ['page' => ['offset' => $offset + $limit]]));
        }

        return $paginationLinks;
    }

    private function sort($query, Context $context)
    {
        $schema = $this->resource->getSchema();

        if (! $sort = $context->getRequest()->getQueryParams()['sort'] ?? $schema->getDefaultSort()) {
            return;
        }

        $adapter = $this->resource->getAdapter();
        $sortFields = $schema->getSortFields();
        $fields = $schema->getFields();

        foreach ($this->parseSort($sort) as $name => $direction) {
            if (isset($sortFields[$name])) {
                $sortFields[$name]($query, $direction, $context);
                continue;
            }

            if (
                isset($fields[$name])
                && $fields[$name] instanceof Attribute
                && evaluate($fields[$name]->getSortable(), [$context])
            ) {
                $adapter->sortByAttribute($query, $fields[$name], $direction);
                continue;
            }

            throw new BadRequestException("Invalid sort field [$name]", 'sort');
        }
    }

    private function parseSort(string $string): array
    {
        $sort = [];

        foreach (explode(',', $string) as $field) {
            if ($field[0] === '-') {
                $field = substr($field, 1);
                $direction = 'desc';
            } else {
                $direction = 'asc';
            }

            $sort[$field] = $direction;
        }

        return $sort;
    }

    private function paginate($query, Context $context)
    {
        $schema = $this->resource->getSchema();
        $queryParams = $context->getRequest()->getQueryParams();
        $limit = $schema->getPerPage();

        if (isset($queryParams['page']['limit'])) {
            $limit = $queryParams['page']['limit'];

            if (! ctype_digit(strval($limit)) || $limit < 1) {
                throw new BadRequestException('page[limit] must be a positive integer', 'page[limit]');
            }

            $limit = min($schema->getLimit(), $limit);
        }

        $offset = 0;

        if (isset($queryParams['page']['offset'])) {
            $offset = $queryParams['page']['offset'];

            if (! ctype_digit(strval($offset)) || $offset < 0) {
                throw new BadRequestException('page[offset] must be a non-negative integer', 'page[offset]');
            }
        }

        if ($limit || $offset) {
            $this->resource->getAdapter()->paginate($query, $limit, $offset);
        }

        return [$offset, $limit];
    }

    private function filter($query, Context $context)
    {
        if (! $filter = $context->getRequest()->getQueryParams()['filter'] ?? null) {
            return;
        }

        if (! is_array($filter)) {
            throw new BadRequestException('filter must be an array', 'filter');
        }

        $schema = $this->resource->getSchema();
        $adapter = $this->resource->getAdapter();
        $filters = $schema->getFilters();
        $fields = $schema->getFields();

        foreach ($filter as $name => $value) {
            if ($name === 'id') {
                $adapter->filterByIds($query, explode(',', $value));
                continue;
            }

            if (isset($filters[$name]) && evaluate($filters[$name]->getVisible(), [$context])) {
                $filters[$name]->getCallback()($query, $value, $context);
                continue;
            }

            if (isset($fields[$name]) && evaluate($fields[$name]->getFilterable(), [$context])) {
                if ($fields[$name] instanceof Attribute) {
                    $this->filterByAttribute($adapter, $query, $fields[$name], $value);
                } elseif ($fields[$name] instanceof HasOne) {
                    $value = array_filter(explode(',', $value));
                    $adapter->filterByHasOne($query, $fields[$name], $value);
                } elseif ($fields[$name] instanceof HasMany) {
                    $value = array_filter(explode(',', $value));
                    $adapter->filterByHasMany($query, $fields[$name], $value);
                }
                continue;
            }

            throw new BadRequestException("Invalid filter [$name]", "filter[$name]");
        }
    }

    private function filterByAttribute(AdapterInterface $adapter, $query, Attribute $attribute, $value)
    {
        if (preg_match('/(.+)\.\.(.+)/', $value, $matches)) {
            if ($matches[1] !== '*') {
                $adapter->filterByAttribute($query, $attribute, $value, '>=');
            }
            if ($matches[2] !== '*') {
                $adapter->filterByAttribute($query, $attribute, $value, '<=');
            }

            return;
        }

        foreach (['>=', '>', '<=', '<'] as $operator) {
            if (strpos($value, $operator) === 0) {
                $adapter->filterByAttribute($query, $attribute, substr($value, strlen($operator)), $operator);

                return;
            }
        }

        $adapter->filterByAttribute($query, $attribute, $value);
    }
}
