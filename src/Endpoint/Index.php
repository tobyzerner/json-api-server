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
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\ResourceType;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\Meta;
use Tobyz\JsonApiServer\Serializer;

use function Tobyz\JsonApiServer\evaluate;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\run_callbacks;

class Index
{
    use Concerns\IncludesData;

    /**
     * Handle a request to show a resource listing.
     */
    public function handle(Context $context, ResourceType $resourceType): ResponseInterface
    {
        $adapter = $resourceType->getAdapter();
        $schema = $resourceType->getSchema();

        if (! evaluate($schema->isListable(), [$context])) {
            throw new ForbiddenException();
        }

        $query = $adapter->query();

        $resourceType->scope($query, $context);

        $include = $this->getInclude($context, $resourceType);

        [$offset, $limit] = $this->paginate($resourceType, $query, $context);
        $this->sort($resourceType, $query, $context);

        if ($filter = $context->getRequest()->getQueryParams()['filter'] ?? null) {
            $resourceType->filter($query, $filter, $context);
        }

        run_callbacks($schema->getListeners('listing'), [$query, $context]);

        $total = $schema->isCountable() ? $adapter->count($query) : null;
        $models = $adapter->get($query);

        run_callbacks($schema->getListeners('listed'), [$models, $context]);

        $serializer = new Serializer($context);

        foreach ($models as $model) {
            $serializer->add($resourceType, $model, $include);
        }

        [$primary, $included] = $serializer->serialize();

        $meta = array_values(array_map(function (Meta $meta) use ($context) {
            return new Structure\Meta($meta->getName(), $meta->getValue()($context));
        }, $context->getMeta()));

        return json_api_response(
            new Structure\CompoundDocument(
                new Structure\PaginatedCollection(
                    new Structure\Pagination(...$this->buildPaginationLinks($resourceType, $context->getRequest(), $offset, $limit, count($models), $total)),
                    new Structure\ResourceCollection(...$primary)
                ),
                new Structure\Included(...$included),
                new Structure\Link\SelfLink($this->buildUrl($context->getRequest())),
                new Structure\Meta('offset', $offset),
                new Structure\Meta('limit', $limit),
                ...($total !== null ? [new Structure\Meta('total', $total)] : []),
                ...$meta
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

    private function buildPaginationLinks(ResourceType $resourceType, Request $request, int $offset, ?int $limit, int $count, ?int $total): array
    {
        $paginationLinks = [];
        $schema = $resourceType->getSchema();

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

        if ($schema->isCountable() && $schema->getPerPage() && $limit && $offset + $limit < $total) {
            $paginationLinks[] = new LastLink($this->buildUrl($request, ['page' => ['offset' => floor(($total - 1) / $limit) * $limit]]));
        }

        if (($total === null && $count === $limit) || $offset + $count < $total) {
            $paginationLinks[] = new NextLink($this->buildUrl($request, ['page' => ['offset' => $offset + $limit]]));
        }

        return $paginationLinks;
    }

    private function sort(ResourceType $resourceType, $query, Context $context): void
    {
        $schema = $resourceType->getSchema();

        if (! $sort = $context->getRequest()->getQueryParams()['sort'] ?? $schema->getDefaultSort()) {
            return;
        }

        $adapter = $resourceType->getAdapter();
        $sorts = $schema->getSorts();
        $fields = $schema->getFields();

        foreach ($this->parseSort($sort) as $name => $direction) {
            if (isset($sorts[$name]) && evaluate($sorts[$name]->getVisible(), [$context])) {
                $sorts[$name]->getCallback()($query, $direction, $context);
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

            throw (new BadRequestException("Invalid sort field [$name]"))->setSourceParameter('sort');
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

    private function paginate(ResourceType $resourceType, $query, Context $context): array
    {
        $schema = $resourceType->getSchema();
        $queryParams = $context->getRequest()->getQueryParams();
        $limit = $schema->getPerPage();

        if (isset($queryParams['page']['limit'])) {
            $limit = $queryParams['page']['limit'];

            if (! ctype_digit(strval($limit)) || $limit < 1) {
                throw (new BadRequestException('page[limit] must be a positive integer'))->setSourceParameter('page[limit]');
            }

            $limit = min($schema->getLimit(), $limit);
        }

        $offset = 0;

        if (isset($queryParams['page']['offset'])) {
            $offset = $queryParams['page']['offset'];

            if (! ctype_digit(strval($offset)) || $offset < 0) {
                throw (new BadRequestException('page[offset] must be a non-negative integer'))->setSourceParameter('page[offset]');
            }
        }

        if ($limit || $offset) {
            $resourceType->getAdapter()->paginate($query, $limit, $offset);
        }

        return [$offset, $limit];
    }
}
