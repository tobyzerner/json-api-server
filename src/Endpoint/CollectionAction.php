<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasSchema;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\json_api_response;

class CollectionAction implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use HasDescription;
    use HasResponse;
    use HasSchema;
    use BuildsDocument;

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

        $response = json_api_response($this->buildDocument($context), status: 204);

        return $this->applyResponseHooks($response, $context);
    }

    public function getOpenApiPaths(Collection $collection, JsonApi $api): array
    {
        $response = [];

        if ($headers = $this->getHeadersSchema($api)) {
            $response['headers'] = $headers;
        }

        $paths = [
            "/{$collection->name()}/$this->name" => [
                strtolower($this->method) => [
                    'description' => $this->getDescription(),
                    'tags' => [$collection->name()],
                    'responses' => [
                        '204' => $response,
                    ],
                ],
            ],
        ];

        return $this->mergeSchema($paths);
    }
}
