<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsResourceDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasSchema;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\set_value;

class Create implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use HasDescription;
    use HasResponse;
    use HasSchema;
    use SavesData;
    use ShowsResources;
    use BuildsResourceDocument;
    use BuildsOpenApiPaths;

    private ?string $asyncCollection = null;
    private ?Closure $asyncCallback = null;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        if (str_contains($context->path(), '/')) {
            return null;
        }

        if ($context->request->getMethod() !== 'POST') {
            throw new MethodNotAllowedException();
        }

        $collection = $context->collection;

        if (!$collection instanceof Creatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($collection), Creatable::class),
            );
        }

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        $data = $this->parseData($context);

        $context = $context
            ->withResource($resource = $context->resource($data['type']))
            ->withModel($model = $collection->newModel($context))
            ->withData($data);

        $this->assertFieldsValid($context, true);
        $this->fillDefaultValues($context);
        $this->deserializeValues($context, true);
        $this->assertDataValid($context, true);
        $this->setValues($context, true);

        if ($this->asyncCallback) {
            $asyncResult = ($this->asyncCallback)($model, $context);

            if ($asyncResult !== null) {
                if (is_string($asyncResult)) {
                    $response = $context
                        ->createResponse($this->buildDocument($context))
                        ->withHeader(
                            'Location',
                            $context->api->basePath . '/' . ltrim($asyncResult, '/'),
                        );
                } else {
                    $context = $context->forModel([$this->asyncCollection], $asyncResult);

                    $response = $context
                        ->createResponse($this->buildResourceDocument($asyncResult, $context))
                        ->withHeader(
                            'Content-Location',
                            implode('/', [
                                $context->api->basePath,
                                $context->collection->name(),
                                $context->id($context->resource, $asyncResult),
                            ]),
                        );
                }

                return $response->withStatus(202);
            }
        }

        $context = $context->withModel($model = $resource->create($model, $context));

        $this->saveFields($context, true);

        $response = $context
            ->createResponse($document = $this->buildResourceDocument($model, $context))
            ->withStatus(201);

        if ($location = $document['data']['links']['self'] ?? null) {
            $response = $response->withHeader('Location', $location);
        }

        return $this->applyResponseHooks($response, $context);
    }

    public function async(string $collection, Closure $callback): static
    {
        $this->asyncCollection = $collection;
        $this->asyncCallback = $callback;

        return $this;
    }

    private function fillDefaultValues(Context $context): void
    {
        foreach ($this->getFields($context, true) as $field) {
            if (!has_value($context->data, $field) && ($default = $field->default)) {
                set_value($context->data, $field, $default($context->withField($field)));
            }
        }
    }

    public function getOpenApiPaths(Collection $collection, JsonApi $api): array
    {
        $type = $collection->name();

        $responses = [
            '201' => [
                'content' => $this->buildOpenApiContent(
                    array_map(
                        fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                        $collection->resources(),
                    ),
                ),
            ],
        ];

        if ($headers = $this->getHeadersSchema($api)) {
            $responses['201']['headers'] = $headers;
        }

        if ($this->asyncCollection) {
            $asyncCollection = $api->getCollection($this->asyncCollection);

            $responses['202'] = [
                'headers' => [
                    'Content-Location' => ['schema' => ['type' => 'string']],
                ],
                'content' => $this->buildOpenApiContent(
                    array_map(
                        fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                        $asyncCollection->resources(),
                    ),
                ),
            ];
        }

        $paths = [
            "/$type" => [
                'post' => [
                    'description' => $this->getDescription() ?: "Create $type resource",
                    'tags' => [$type],
                    'requestBody' => [
                        'required' => true,
                        'content' => $this->buildOpenApiContent(
                            array_map(
                                fn($resource) => [
                                    '$ref' => "#/components/schemas/{$resource}_create",
                                ],
                                $collection->resources(),
                            ),
                        ),
                    ],
                    'responses' => $responses,
                ],
            ],
        ];

        return $this->mergeSchema($paths);
    }
}
