<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\MutatesResource;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesRelationship;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesRelationshipDocument;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\Field\InvalidFieldValueException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Resource\Attachable;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

use function Tobyz\JsonApiServer\resolve_value;

class UpdateRelationship implements Endpoint, ProvidesRootSchema, ProvidesRelationshipLinks
{
    use HasParameters;
    use HasResponse;
    use ResolvesModel;
    use ResolvesRelationship;
    use MutatesResource;
    use SerializesRelationshipDocument;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = $context->pathSegments();

        if (count($segments) !== 3 || $segments[1] !== 'relationships') {
            return null;
        }

        $method = strtoupper($context->method());

        if (!in_array($method, ['PATCH', 'POST', 'DELETE'], true)) {
            throw new MethodNotAllowedException();
        }

        $context = $this->resolveModel($context, $segments[0]);

        if (!($context->resource instanceof Updatable)) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($context->resource), Updatable::class),
            );
        }

        $context = $context->withField(
            $field = $this->resolveRelationshipField($context, $segments[2]),
        );

        $this->assertFieldWritable($context, $field);

        $context = $context->withParameters($this->parameters);

        $value = $field->deserializeValue($context->body() ?? [], $context);

        if ($method === 'PATCH') {
            return $this->replaceRelationship($context, $field, $value);
        }

        if ($field instanceof ToMany) {
            if ($method === 'POST') {
                return $this->toggleRelationship($context, $field, $value, true);
            }

            if ($method === 'DELETE') {
                return $this->toggleRelationship($context, $field, $value, false);
            }
        }

        throw new MethodNotAllowedException();
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();
        $paths = [];

        foreach ($context->collection->resources() as $resourceName) {
            $resource = $context->resource($resourceName);
            $context = $context->withResource($resource);

            foreach ($resource->fields() as $field) {
                if (!$field instanceof Relationship || !$field->writable) {
                    continue;
                }

                $updateOperation = [
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
                            $this->parameters,
                        ),
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            JsonApi::MEDIA_TYPE => [
                                'schema' => $this->relationshipDocumentSchema($context, [
                                    '$ref' => "#/components/schemas/{$resource->type()}_relationship_{$field->name}",
                                ]),
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Relationship updated successfully.',
                            ...$this->responseSchema(
                                $this->relationshipDocumentSchema($context, [
                                    '$ref' => "#/components/schemas/{$resource->type()}_relationship_{$field->name}",
                                ]),
                                $context,
                            ),
                        ],
                    ],
                ];

                $operations = [
                    'patch' => [
                        'description' => "Replace $field->name relationship",
                        ...$updateOperation,
                    ],
                ];

                if ($field instanceof ToMany) {
                    $operations['post'] = [
                        'description' => "Attach to $field->name relationship",
                        ...$updateOperation,
                    ];

                    $operations['delete'] = [
                        'description' => "Detach from $field->name relationship",
                        ...$updateOperation,
                    ];
                }

                $paths["/$type/{id}/relationships/$field->name"] = $operations;
            }
        }

        return ['paths' => $paths];
    }

    private function showRelationship(Context $context, Relationship $field): ResponseInterface
    {
        return $this->createResponse(
            $this->serializeRelationshipDocument(
                $field,
                resolve_value($context->resource->getValue($context->model, $field, $context)),
                $context,
            ),
            $context,
        );
    }

    private function replaceRelationship(
        Context $context,
        Relationship $field,
        mixed $value,
    ): ResponseInterface {
        if ($errors = $this->validateField($context, $field, $value)) {
            throw new JsonApiErrorsException($errors);
        }

        $field->setValue($context->model, $value, $context);

        $context = $context->withModel($context->resource->update($context->model, $context));

        $field->saveValue($context->model, $value, $context);

        return $this->showRelationship($context, $field);
    }

    private function toggleRelationship(
        Context $context,
        ToMany $field,
        array $value,
        bool $attach,
    ): ResponseInterface {
        if ($field->attachable) {
            if (!$context->resource instanceof Attachable) {
                throw new RuntimeException(
                    sprintf(
                        '%s must implement %s',
                        get_class($context->resource),
                        Attachable::class,
                    ),
                );
            }

            $this->assertToggleValid($field, $value, $context, $attach);

            if ($attach) {
                $context->resource->attach($context->model, $field, $value, $context);
            } else {
                $context->resource->detach($context->model, $field, $value, $context);
            }

            return $this->showRelationship($context, $field);
        }

        $current = $this->createResourceMap(
            resolve_value($context->resource->getValue($context->model, $field, $context)) ?: [],
            $field,
            $context,
        );

        $delta = $this->createResourceMap($value, $field, $context);

        $new = array_values(
            $attach ? array_merge($current, $delta) : array_diff_key($current, $delta),
        );

        return $this->replaceRelationship($context, $field, $new);
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

    public function relationshipLinks(Relationship $field, SchemaContext $context): array
    {
        return [
            Link::make('self')->get(
                fn($model, Context $context) => $this->resourceSelfLink($model, $context) .
                    '/relationships/' .
                    $field->name,
            ),
        ];
    }
}
