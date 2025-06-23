<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Creatable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

use function Tobyz\JsonApiServer\has_value;
use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\set_value;

class Create implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use SavesData;
    use ShowsResources;
    use HasDescription;
    use BuildsOpenApiPaths;

    private array $afterCallbacks = [];

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        if (str_contains($context->path(), '/')) {
            return null;
        }

        if ($context->request->getMethod() !== 'POST') {
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

        $data = $this->parseData($context);

        $context = $context
            ->withResource($resource = $context->resource($data['type']))
            ->withModel($model = $collection->newModel($context));

        $this->assertFieldsValid($context, $data, true);
        $this->fillDefaultValues($context, $data);
        $this->deserializeValues($context, $data);
        $this->assertDataValid($context, $data, true);
        $this->setValues($context, $data);

        $context = $context->withModel($model = $resource->create($model, $context));

        $this->saveFields($context, $data);

        foreach ($this->afterCallbacks as $callback) {
            $callback($model, $context);
        }

        $response = json_api_response(
            $document = $this->showResource($context, $model),
        )->withStatus(201);

        if ($location = $document['data']['links']['self'] ?? null) {
            $response = $response->withHeader('Location', $location);
        }

        return $response;
    }

    public function after(callable $callback): static
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    private function fillDefaultValues(Context $context, array &$data): void
    {
        foreach ($context->fields($context->resource) as $field) {
            if (!has_value($data, $field) && ($default = $field->default)) {
                set_value($data, $field, $default($context->withField($field)));
            }
        }
    }

    public function getOpenApiPaths(Collection $collection): array
    {
        return [
            "/{$collection->name()}" => [
                'post' => [
                    'description' => $this->getDescription(),
                    'tags' => [$collection->name()],
                    'parameters' => $this->buildOpenApiParameters($collection),
                    'requestBody' => [
                        'required' => true,
                        'content' => $this->buildOpenApiContent(
                            array_map(
                                fn($resource) => [
                                    '$ref' => "#/components/schemas/{$resource}Create",
                                ],
                                $collection->resources(),
                            ),
                        ),
                    ],
                    'responses' => [
                        '200' => [
                            'content' => $this->buildOpenApiContent(
                                array_map(
                                    fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                                    $collection->resources(),
                                ),
                            ),
                        ],
                    ],
                ],
            ],
        ];
    }
}
