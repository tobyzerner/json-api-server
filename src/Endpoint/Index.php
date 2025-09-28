<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\IncludesData;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Pagination\CursorPagination;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Countable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Serializer;

use function Tobyz\JsonApiServer\apply_filters;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\parse_sort_string;

class Index implements Endpoint, OpenApiPathsProvider
{
    use HasMeta;
    use HasVisibility;
    use IncludesData;
    use HasDescription;
    use BuildsOpenApiPaths;

    public ?Pagination $pagination = null;
    public ?string $defaultSort = null;

    public static function make(): static
    {
        return new static();
    }

    public function paginate(int $defaultLimit = 20, ?int $maxLimit = 50): static
    {
        $this->pagination = new OffsetPagination($defaultLimit, $maxLimit);

        return $this;
    }

    public function cursorPaginate(int $defaultSize = 20, ?int $maxSize = 50): static
    {
        $this->pagination = new CursorPagination($defaultSize, $maxSize);

        return $this;
    }

    public function defaultSort(?string $defaultSort): static
    {
        $this->defaultSort = $defaultSort;

        return $this;
    }

    public function handle(Context $context): ?Response
    {
        if (str_contains($context->path(), '/')) {
            return null;
        }

        if ($context->request->getMethod() !== 'GET') {
            throw new MethodNotAllowedException();
        }

        $collection = $context->collection;

        if (!$collection instanceof Listable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Listable::class),
            );
        }

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        $query = $collection->query($context);

        $context = $context->withQuery($query);

        $this->applySorts($query, $context);
        $this->applyFilters($query, $context);

        if (
            $collection instanceof Countable &&
            !is_null($total = $collection->count($query, $context))
        ) {
            $context->documentMeta['page']['total'] = $total;
        }

        if ($this->pagination) {
            $models = $this->pagination->paginate($query, $context);
        } else {
            $models = $collection->results($query, $context);
        }

        $serializer = new Serializer($context);

        $include = $this->getInclude($context);

        foreach ($models as $model) {
            $serializer->addPrimary(
                $context->resource($collection->resource($model, $context)),
                $model,
                $include,
            );
        }

        [$data, $included] = $serializer->serialize();

        $meta = $context->documentMeta;

        foreach ($this->serializeMeta($context) as $key => $value) {
            $meta[$key] = $value;
        }

        $links = $context->documentLinks;

        return json_api_response(compact('data', 'included', 'meta', 'links'));
    }

    private function applySorts($query, Context $context): void
    {
        if (!($sortString = $context->queryParam('sort', $this->defaultSort))) {
            return;
        }

        $sorts = $context->collection->sorts();

        foreach (parse_sort_string($sortString) as [$name, $direction]) {
            foreach ($sorts as $field) {
                if ($field->name === $name && $field->isVisible($context)) {
                    $field->apply($query, $direction, $context);
                    continue 2;
                }
            }

            throw (new BadRequestException("Invalid sort: $name"))->setSource([
                'parameter' => 'sort',
            ]);
        }
    }

    private function applyFilters($query, Context $context): void
    {
        if (!($filters = $context->queryParam('filter'))) {
            return;
        }

        if (!is_array($filters)) {
            throw (new BadRequestException('filter must be an array'))->setSource([
                'parameter' => 'filter',
            ]);
        }

        try {
            apply_filters($query, $filters, $context->collection, $context);
        } catch (Sourceable $e) {
            throw $e->prependSource(['parameter' => 'filter']);
        }
    }

    public function getOpenApiPaths(Collection $collection): array
    {
        return [
            "/{$collection->name()}" => [
                'get' => [
                    'description' => $this->getDescription(),
                    'tags' => [$collection->name()],
                    'responses' => [
                        '200' => [
                            'content' => $this->buildOpenApiContent(
                                array_map(
                                    fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                                    $collection->resources(),
                                ),
                                multiple: true,
                            ),
                        ],
                    ],
                ],
            ],
        ];
    }
}
