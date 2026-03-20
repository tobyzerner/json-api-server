<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesResourceDocument;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\SchemaContext;

class ResourceAction implements Endpoint, ProvidesRootSchema
{
    use BuildsOpenApiPaths;
    use HasVisibility;
    use HasParameters;
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

        $context = $context->withParameters($this->getParameters());

        if ($response = ($this->handler)($context->model, $context)) {
            return $this->applyResponseHooks($response, $context);
        }

        return $this->createResponse(
            $this->serializeResourceDocument($context->model, $context),
            $context,
        );
    }

    protected function getParameters(): array
    {
        return [...$this->resourceDocumentParameters(), ...$this->parameters];
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();

        return [
            'paths' => [
                "/$type/{id}/$this->name" => [
                    strtolower($this->method) => $this->mergeSchema([
                        'tags' => [$type],
                        'parameters' => $this->openApiResourceParameters(
                            $context,
                            $this->getParameters(),
                        ),
                        'responses' => [
                            '200' => [
                                'description' => 'Action performed successfully.',
                                ...$this->responseSchema(
                                    $this->resourceDocumentSchema(
                                        $context,
                                        $this->openApiSchemaRefs($context->collection->resources()),
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
}
