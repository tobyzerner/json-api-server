<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasSummary;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\json_api_response;

class Show implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use FindsResources;
    use ShowsResources;
    use HasSummary;
    use HasDescription;
    use BuildsOpenApiPaths;

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

        if ($context->request->getMethod() !== 'GET') {
            throw new MethodNotAllowedException();
        }

        $model = $this->findResource($context, $segments[1]);

        if (!$this->isVisible($context = $context->withModel($model))) {
            throw new ForbiddenException();
        }

        return json_api_response($this->showResource($context, $model));
    }

    public function getOpenApiPaths(Collection $collection): array
    {
        $parameters = array_merge(
            [
                [
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ],
            ],
            $this->buildOpenApiParameters($collection)
        );

        return [
            "/{$collection->name()}/{id}" => [
                'get' => [
                    'summary' => $this->getSummary(),
                    'description' => $this->getDescription(),
                    'tags' => [$collection->name()],
                    'parameters' => $parameters,
                    'responses' => [
                        '200' => [
                            'description' => 'Successful show response.',
                            'content' => $this->buildOpenApiContent(
                                array_map(
                                    fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                                    $collection->resources(),
                                ),
                            ),
                        ],
                        '400' => $this->buildBadRequestErrorResponse(),
                        '401' => $this->buildUnauthorizedErrorResponse(),
                        '403' => $this->buildForbiddenErrorResponse(),
                        '404' => $this->buildNotFoundErrorResponse(),
                        '500' => $this->buildInternalServerErrorResponse(),
                    ],
                ],
            ],
        ];
    }
}
