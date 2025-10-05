<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsRelationshipDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsResourceDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasSchema;
use Tobyz\JsonApiServer\Endpoint\Concerns\SavesData;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\Field\InvalidFieldValueException;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Attachable;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;

use function Tobyz\JsonApiServer\resolve_value;

class Update implements Endpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use HasDescription;
    use HasResponse;
    use HasSchema;
    use FindsResources;
    use SavesData;
    use ShowsResources;
    use BuildsResourceDocument;
    use BuildsRelationshipDocument;
    use BuildsOpenApiPaths;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = explode('/', $context->path());
        $count = count($segments);

        if ($count !== 2 && ($count !== 4 || $segments[2] !== 'relationships')) {
            return null;
        }

        $method = $context->request->getMethod();

        if (
            ($count === 2 && $method !== 'PATCH') ||
            ($count === 4 && !in_array($method, ['PATCH', 'POST', 'DELETE']))
        ) {
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

        if ($count === 2) {
            return $this->updateResource($resource, $model, $context);
        }

        $field = $context->fields($context->resource)[$segments[3]] ?? null;

        if (!$field instanceof Relationship) {
            throw new NotFoundException();
        }

        $this->assertFieldWritable($context, $field);

        $value = $field->deserializeValue($context->body() ?? [], $context);

        if ($method === 'PATCH') {
            return $this->replaceRelationship($resource, $model, $field, $value, $context);
        }

        if ($field instanceof ToMany && $method === 'POST') {
            return $this->toggleRelationship($resource, $model, $field, $value, $context, true);
        }

        if ($field instanceof ToMany && $method === 'DELETE') {
            return $this->toggleRelationship($resource, $model, $field, $value, $context, false);
        }

        throw new MethodNotAllowedException();
    }

    public function getOpenApiPaths(Collection $collection, JsonApi $api): array
    {
        $type = $collection->name();

        $idParameter = [
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ],
        ];

        $response = [
            'content' => $this->buildOpenApiContent(
                array_map(
                    fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                    $collection->resources(),
                ),
            ),
        ];

        if ($headers = $this->getHeadersSchema($api)) {
            $response['headers'] = $headers;
        }

        $paths = [
            "/$type/{id}" => [
                'patch' => [
                    'description' => $this->getDescription() ?: "Update $type resource",
                    'tags' => [$type],
                    'parameters' => $idParameter,
                    'requestBody' => [
                        'required' => true,
                        'content' => $this->buildOpenApiContent(
                            array_map(
                                fn($resource) => [
                                    '$ref' => "#/components/schemas/{$resource}_update",
                                ],
                                $collection->resources(),
                            ),
                        ),
                    ],
                    'responses' => [
                        '200' => $response,
                    ],
                ],
            ],
        ];

        foreach ($collection->resources() as $resource) {
            $resource = $api->getResource($resource);

            foreach ($resource->fields() as $field) {
                if (!$field instanceof Relationship || !$field->writable) {
                    continue;
                }

                $paths["/$type/{id}/relationships/$field->name"]['patch'] = [
                    'description' => "Replace $field->name relationship",
                    'tags' => [$type],
                    'parameters' => $idParameter,
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            JsonApi::MEDIA_TYPE => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => [
                                            '$ref' => "#/components/schemas/{$type}_{$field->name}/properties/data",
                                        ],
                                    ],
                                    'required' => ['data'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                JsonApi::MEDIA_TYPE => [
                                    'schema' => [
                                        '$ref' => "#/components/schemas/{$type}_{$field->name}",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                if (!$field instanceof ToMany) {
                    continue;
                }

                $togglePath = [
                    'tags' => [$type],
                    'parameters' => $idParameter,
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            JsonApi::MEDIA_TYPE => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'data' => [
                                            '$ref' => "#/components/schemas/{$type}_{$field->name}/properties/data",
                                        ],
                                    ],
                                    'required' => ['data'],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'content' => [
                                JsonApi::MEDIA_TYPE => [
                                    'schema' => [
                                        '$ref' => "#/components/schemas/{$type}_{$field->name}",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                $paths["/$type/{id}/relationships/$field->name"]['post'] = array_merge(
                    ['description' => "Attach to $field->name relationship"],
                    $togglePath,
                );

                $paths["/$type/{id}/relationships/$field->name"]['delete'] = array_merge(
                    ['description' => "Detach from $field->name relationship"],
                    $togglePath,
                );
            }
        }

        return $this->mergeSchema($paths);
    }

    private function updateResource(
        Updatable $resource,
        object $model,
        Context $context,
    ): ResponseInterface {
        $data = $this->parseData($context);

        $context = $context->withData($data);

        $this->assertFieldsValid($context);
        $this->deserializeValues($context);
        $this->assertDataValid($context, false);
        $this->setValues($context);

        $context = $context->withModel($model = $resource->update($model, $context));

        $this->saveFields($context);

        $response = $context->createResponse($this->buildResourceDocument($model, $context));

        return $this->applyResponseHooks($response, $context);
    }

    private function showRelationship(
        Resource $resource,
        object $model,
        Relationship $field,
        Context $context,
    ): ResponseInterface {
        return $context->createResponse(
            $this->buildRelationshipDocument(
                $field,
                resolve_value($resource->getValue($model, $field, $context)),
                $context,
            ),
        );
    }

    private function replaceRelationship(
        Resource&Updatable $resource,
        object $model,
        Relationship $field,
        mixed $value,
        Context $context,
    ): ResponseInterface {
        if ($errors = $this->validateField($context, $field, $value)) {
            throw new JsonApiErrorsException($errors);
        }

        $field->setValue($context->model, $value, $context);

        $context = $context->withModel($model = $resource->update($model, $context));

        $field->saveValue($context->model, $value, $context);

        return $this->showRelationship($resource, $model, $field, $context);
    }

    private function toggleRelationship(
        Resource&Updatable $resource,
        object $model,
        ToMany $field,
        array $value,
        Context $context,
        bool $attach,
    ): ResponseInterface {
        if ($field->attachable) {
            if (!$resource instanceof Attachable) {
                throw new RuntimeException(
                    sprintf('%s must implement %s', get_class($resource), Attachable::class),
                );
            }

            $this->assertToggleValid($field, $value, $context, $attach);

            if ($attach) {
                $resource->attach($model, $field, $value, $context);
            } else {
                $resource->detach($model, $field, $value, $context);
            }

            return $this->showRelationship($resource, $model, $field, $context);
        }

        $current = $this->createResourceMap(
            resolve_value($resource->getValue($model, $field, $context)) ?: [],
            $field,
            $context,
        );

        $delta = $this->createResourceMap($value, $field, $context);

        $new = array_values(
            $attach ? array_merge($current, $delta) : array_diff_key($current, $delta),
        );

        return $this->replaceRelationship($resource, $model, $field, $new, $context);
    }

    private function createResourceMap(array $models, Relationship $field, Context $context): array
    {
        $map = [];

        foreach ($models as $model) {
            $modelContext = $context->forModel($field->collections, $model);
            $resource = $modelContext->resource;
            $map[$resource->type() . '-' . $modelContext->id($resource, $model)] = $model;
        }

        return $map;
    }

    private function assertToggleValid(
        ToMany $field,
        array $value,
        Context $context,
        bool $attach,
    ): void {
        $validators = $attach ? $field->attachValidators : $field->detachValidators;
        $errors = [];

        foreach ($validators as $validator) {
            $validator(
                function ($error = [], ?int $index = null) use (&$errors) {
                    if (!$error instanceof ErrorProvider) {
                        $error = new InvalidFieldValueException(
                            is_scalar($error) ? ['detail' => (string) $error] : $error,
                        );
                    }

                    if ($index !== null) {
                        $error->source(['pointer' => "/data/$index"]);
                    }

                    $errors[] = $error;
                },
                $value,
                $context->model,
                $context,
            );
        }

        if ($errors) {
            throw new JsonApiErrorsException($errors);
        }
    }
}
