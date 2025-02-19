<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\json_api_response;

class Update implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use FindsResources;
    use SavesData;
    use ShowsResources;
    use HasDescription;

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

        if ($context->request->getMethod() !== 'PATCH') {
            throw new MethodNotAllowedException();
        }

        $model = $this->findResource($context, $segments[1]);

        $context = $context
            ->withModel($model)
            ->withResource(
                $resource = $context->resource($context->collection->resource($model, $context)),
            );

        if (!$resource instanceof Updatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($resource), Updatable::class),
            );
        }

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        $data = $this->parseData($context);

        $this->assertFieldsValid($context, $data);
        $this->deserializeValues($context, $data);
        $this->assertDataValid($context, $data, false);
        $this->setValues($context, $data);

        $context = $context->withModel($model = $resource->update($model, $context));

        $this->saveFields($context, $data);

        return json_api_response($this->showResource($context, $model));
    }

    public function getOpenApiPaths(Collection $collection): array
    {
        $resourcesUpdate = array_map(
            fn($resource) => ['$ref' => "#/components/schemas/{$resource}Update"],
            $collection->resources(),
        );

        $resources = array_map(
            fn($resource) => [
                '$ref' => "#/components/schemas/$resource",
            ],
            $collection->resources(),
        );

        return [
            "/{$collection->name()}/{id}" => [
                'patch' => [
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
                    'requestBody' => [
                        'content' => [
                            JsonApi::MEDIA_TYPE => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['data'],
                                    'properties' => [
                                        'data' =>
                                            count($resourcesUpdate) === 1
                                                ? $resourcesUpdate[0]
                                                : ['oneOf' => $resourcesUpdate],
                                    ],
                                ],
                            ],
                        ],
                        'required' => true,
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
