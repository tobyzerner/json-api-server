<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\json_api_response;

class ResourceAction implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use HasDescription;
    use FindsResources;
    use ShowsResources;

    public string $method = 'POST';

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
        $segments = explode('/', $context->path());

        if (count($segments) !== 3 || $segments[2] !== $this->name) {
            return null;
        }

        if ($context->request->getMethod() !== $this->method) {
            throw new MethodNotAllowedException();
        }

        $model = $this->findResource($context, $segments[1]);

        $context = $context
            ->withModel($model)
            ->withResource($context->resource($context->collection->resource($model, $context)));

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        ($this->handler)($model, $context);

        return json_api_response($this->showResource($context, $model));
    }

    public function getOpenApiPaths(Collection $collection): array
    {
        $resources = array_map(
            fn($resource) => [
                '$ref' => "#/components/schemas/$resource",
            ],
            $collection->resources(),
        );

        return [
            "/{$collection->name()}/{id}/{$this->name}" => [
                strtolower($this->method) => [
                    'description' => $this->getDescription(),
                    'tags' => [$collection->name()],
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                JsonApi::MEDIA_TYPE => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['data'],
                                        'properties' => [
                                            'data' =>
                                                count($resources) === 1
                                                    ? $resources[0]
                                                    : ['oneOf' => $resources],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
