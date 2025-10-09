<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesResourceDocument;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

class ShowResource implements Endpoint, ProvidesRootSchema, ProvidesResourceLinks
{
    use HasParameters;
    use HasResponse;
    use HasSchema;
    use ResolvesModel;
    use SerializesResourceDocument;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = $context->pathSegments();

        if (count($segments) !== 1) {
            return null;
        }

        if ($context->method() !== 'GET') {
            throw new MethodNotAllowedException();
        }

        $context = $this->resolveModel($context, $segments[0]);

        $context = $context->withParameters($this->getParameters());

        return $this->createResponse(
            $this->serializeResourceDocument($context->model, $context),
            $context,
        );
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();

        return [
            'paths' => [
                "/$type/{id}" => [
                    'get' => $this->mergeSchema([
                        'tags' => [$type],
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                            ],
                            ...array_map(
                                fn(Parameter $parameter) => $parameter->getSchema($context),
                                $this->getParameters(),
                            ),
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Successful show response.',
                                ...$this->responseSchema(
                                    $this->resourceDocumentSchema(
                                        $context,
                                        array_map(
                                            fn($resource) => [
                                                '$ref' => "#/components/schemas/$resource",
                                            ],
                                            $context->collection->resources(),
                                        ),
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

    public function seeOther(callable $callback): static
    {
        $this->response(function ($response, $model, $context) use ($callback) {
            $result = $callback($model, $context);

            if ($result !== null) {
                if (!is_string($result)) {
                    throw new RuntimeException('seeOther() callback must return a string');
                }

                $location = $context->api->basePath . '/' . ltrim($result, '/');

                return $context
                    ->createResponse([])
                    ->withStatus(303)
                    ->withHeader('Location', $location);
            }

            return $response;
        });

        $this->schema([
            'responses' => [
                '303' => [
                    'headers' => [
                        'Location' => ['schema' => ['type' => 'string']],
                    ],
                ],
            ],
        ]);

        return $this;
    }

    public function resourceLinks(SchemaContext $context): array
    {
        return [
            Link::make('self')->get(
                fn($model, Context $context) => $this->resourceSelfLink($model, $context),
            ),
        ];
    }

    protected function getParameters(): array
    {
        return [...$this->resourceDocumentParameters(), ...$this->parameters];
    }
}
