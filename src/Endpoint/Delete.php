<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasSchema;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

class Delete implements Endpoint, OpenApiPathsProvider
{
    use HasDescription;
    use HasVisibility;
    use HasResponse;
    use HasSchema;
    use FindsResources;
    use BuildsDocument;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = explode('/', $context->path());

        if (count($segments) !== 2) {
            return null;
        }

        if ($context->request->getMethod() !== 'DELETE') {
            throw new MethodNotAllowedException();
        }

        $model = $this->findResource($context, $segments[1]);

        $context = $context->withResource(
            $resource = $context->resource($context->collection->resource($model, $context)),
        );

        if (!$resource instanceof Deletable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($resource), Deletable::class),
            );
        }

        if (!$this->isVisible($context = $context->withModel($model))) {
            throw new ForbiddenException();
        }

        $resource->delete($model, $context);

        $response = $context->createResponse($this->buildDocument($context))->withStatus(204);

        return $this->applyResponseHooks($response, $context);
    }

    public function getOpenApiPaths(Collection $collection, JsonApi $api): array
    {
        $response = [];

        if ($headers = $this->getHeadersSchema($api)) {
            $response['headers'] = $headers;
        }

        $paths = [
            "/{$collection->name()}/{id}" => [
                'delete' => [
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
                        '204' => $response,
                    ],
                ],
            ],
        ];

        return $this->mergeSchema($paths);
    }
}
