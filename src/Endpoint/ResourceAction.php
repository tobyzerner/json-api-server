<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesResourceDocument;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

class ResourceAction implements Endpoint, ProvidesRootSchema
{
    use HasVisibility;
    use HasResponse;
    use HasSchema;
    use ResolvesModel;
    use SerializesResourceDocument;

    private string $method = 'POST';

    public function __construct(public string $name, public Closure $handler)
    {
    }

    public static function make(string $name, Closure $handler): static
    {
        return new static($name, $handler);
    }

    public function method(string $method): static
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = $context->pathSegments();

        if (count($segments) !== 2 || $segments[1] !== $this->name) {
            return null;
        }

        if (strtoupper($context->method()) !== $this->method) {
            throw new MethodNotAllowedException();
        }

        $context = $this->resolveModel($context, $segments[0]);

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        $context = $context->withParameters($this->resourceDocumentParameters());

        if ($response = ($this->handler)($context->model, $context)) {
            return $this->applyResponseHooks($response, $context);
        }

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
                "/$type/{id}/$this->name" => $this->mergeSchema([
                    strtolower($this->method) => [
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
                                $this->resourceDocumentParameters(),
                            ),
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Action performed successfully.',
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
                    ],
                ]),
            ],
        ];
    }
}
