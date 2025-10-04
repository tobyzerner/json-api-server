<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsResourceDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasSchema;
use Tobyz\JsonApiServer\Endpoint\Concerns\ListsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Pagination\CursorPagination;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\json_api_response;

class Index implements Endpoint, OpenApiPathsProvider
{
    use HasDescription;
    use HasVisibility;
    use HasResponse;
    use HasSchema;
    use ListsResources;
    use BuildsResourceDocument;
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

        $context = $context->withQuery($query = $collection->query($context));

        $models = $this->listResources(
            $query,
            $collection,
            $context,
            $this->defaultSort,
            $this->pagination,
        );

        $document = $this->buildResourceDocument($models, $context);

        $document['links']['self'] ??= $context->currentUrl();

        $response = json_api_response($document);

        return $this->applyResponseHooks($response, $context);
    }

    public function getOpenApiPaths(Collection $collection, JsonApi $api): array
    {
        $response = [
            'content' => $this->buildOpenApiContent(
                array_map(
                    fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                    $collection->resources(),
                ),
                multiple: true,
            ),
        ];

        if ($headers = $this->getHeadersSchema($api)) {
            $response['headers'] = $headers;
        }

        $paths = [
            "/{$collection->name()}" => [
                'get' => [
                    'description' => $this->getDescription(),
                    'tags' => [$collection->name()],
                    'responses' => [
                        '200' => $response,
                    ],
                ],
            ],
        ];

        return $this->mergeSchema($paths);
    }
}
