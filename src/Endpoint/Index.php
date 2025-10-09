<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesList;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesResourceDocument;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Pagination\CursorPagination;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Pagination\Pagination;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

class Index implements Endpoint, ProvidesRootSchema
{
    use HasVisibility;
    use HasResponse;
    use HasSchema;
    use ResolvesList;
    use SerializesResourceDocument;

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
        if ($context->pathSegments()) {
            return null;
        }

        if ($context->method() !== 'GET') {
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

        $context = $context->withParameters($this->getParameters($collection));

        $context = $context->withQuery($query = $collection->query($context));

        $models = $this->resolveList($query, $collection, $context, $this->pagination);

        $document = $this->serializeResourceDocument($models, $context);

        $document['links']['self'] ??= $context->currentUrl();

        return $this->createResponse($document, $context);
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();

        $schemaProviders = [];

        if ($pagination = $this->pagination ?? $context->collection->pagination()) {
            $schemaProviders[] = $pagination;
        }

        return [
            'paths' => [
                "/$type" => [
                    'get' => $this->mergeSchema([
                        'tags' => [$type],
                        'parameters' => array_map(
                            fn(Parameter $parameter) => $parameter->getSchema($context),
                            $this->getParameters($context->collection),
                        ),
                        'responses' => [
                            '200' => [
                                'description' => 'Successful list response.',
                                ...$this->responseSchema(
                                    $this->resourceDocumentSchema(
                                        $context,
                                        array_map(
                                            fn($resource) => [
                                                '$ref' => "#/components/schemas/$resource",
                                            ],
                                            $context->collection->resources(),
                                        ),
                                        multiple: true,
                                        schemaProviders: $schemaProviders,
                                    ),
                                    $context,
                                ),
                            ],
                        ],
                    ]),
                ],
            ],
        ];
    }

    protected function getParameters(Collection $collection): array
    {
        return [
            ...$this->resourceDocumentParameters(),
            ...$this->listParameters($collection, $this->defaultSort, $this->pagination),
        ];
    }
}
