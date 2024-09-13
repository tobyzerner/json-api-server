<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

class CollectionAction implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use HasDescription;

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

        if (count($segments) !== 2 || $segments[1] !== $this->name) {
            return null;
        }

        if ($context->request->getMethod() !== $this->method) {
            throw new MethodNotAllowedException();
        }

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        ($this->handler)($context);

        return new Response(204);
    }

    public function getOpenApiPaths(Collection $collection): array
    {
        return [
            "/{$collection->name()}/$this->name" => [
                'post' => [
                    'description' => $this->getDescription(),
                    'tags' => [$collection->name()],
                    'responses' => [
                        '204' => [],
                    ],
                ],
            ],
        ];
    }
}
