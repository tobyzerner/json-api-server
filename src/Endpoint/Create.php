<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\MutatesResource;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesResourceDocument;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\set_value;

class Create implements Endpoint, ProvidesRootSchema
{
    use HasVisibility;
    use HasParameters;
    use HasResponse;
    use HasSchema;
    use MutatesResource;
    use SerializesResourceDocument;

    private ?string $asyncCollection = null;
    private ?Closure $asyncCallback = null;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        if ($context->pathSegments()) {
            return null;
        }

        if ($context->method() !== 'POST') {
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

        $context = $context->withParameters($this->getParameters());

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
                        ->createResponse($this->serializeDocument($context))
                        ->withHeader(
                            'Location',
                            $context->api->basePath . '/' . ltrim($asyncResult, '/'),
                        );
                } else {
                    $context = $context->forModel([$this->asyncCollection], $asyncResult);

                    $response = $context
                        ->createResponse($this->serializeResourceDocument($asyncResult, $context))
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

        $response = $this->createResponse(
            $document = $this->serializeResourceDocument($model, $context),
            $context,
        )->withStatus(201);

        if ($location = $document['data']['links']['self'] ?? null) {
            $response = $response->withHeader('Location', $location);
        }

        return $response;
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

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();

        $responses = [
            '201' => [
                'description' => 'Resource created successfully.',
                ...$this->responseSchema(
                    $this->resourceDocumentSchema(
                        $context,
                        array_map(
                            fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                            $context->collection->resources(),
                        ),
                    ),
                    $context,
                ),
            ],
        ];

        if ($this->asyncCollection) {
            $asyncCollection = $context->api->getCollection($this->asyncCollection);

            $responses['202'] = [
                'description' => 'Resource accepted for creation.',
                'headers' => ['Content-Location' => ['schema' => ['type' => 'string']]],
                'content' => $this->responseSchema(
                    $this->resourceDocumentSchema(
                        $context,
                        array_map(
                            fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                            $asyncCollection->resources(),
                        ),
                    ),
                    $context,
                ),
            ];
        }

        return [
            'paths' => [
                "/$type" => [
                    'post' => $this->mergeSchema([
                        'tags' => [$type],
                        'parameters' => array_map(
                            fn(Parameter $parameter) => $parameter->getSchema($context),
                            $this->getParameters(),
                        ),
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                JsonApi::MEDIA_TYPE => [
                                    'schema' => $this->resourceDocumentSchema(
                                        $context,
                                        array_map(
                                            fn($resource) => [
                                                '$ref' => "#/components/schemas/{$resource}_create",
                                            ],
                                            $context->collection->resources(),
                                        ),
                                    ),
                                ],
                            ],
                        ],
                        'responses' => $responses,
                    ]),
                ],
            ],
        ];
    }

    protected function getParameters(): array
    {
        return [...$this->resourceDocumentParameters(), ...$this->parameters];
    }
}
