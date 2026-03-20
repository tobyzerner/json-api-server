<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasSavedCallbacks;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\MutatesResource;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesResourceDocument;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Resource\Updatable;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\SchemaContext;

class UpdateResource implements Endpoint, ProvidesRootSchema, ProvidesResourceLinks
{
    use BuildsOpenApiPaths;
    use HasParameters;
    use HasSavedCallbacks;
    use HasResponse;
    use HasSchema;
    use ResolvesModel;
    use MutatesResource;
    use SerializesResourceDocument;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = $context->pathSegments();

        if (count($segments) !== 1) {
            return null;
        }

        if (strtoupper($context->method()) !== 'PATCH') {
            throw new MethodNotAllowedException();
        }

        $context = $this->resolveModel($context, $segments[0]);

        if (!$context->resource instanceof Updatable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($context->resource), Updatable::class),
            );
        }

        $context = $context->withParameters($this->getParameters());

        $data = $this->parseData($context);

        $context = $context->withData($data);

        $this->assertFieldsValid($context);
        $this->deserializeValues($context);
        $this->assertDataValid($context, false);
        $this->setValues($context);

        $context = $context->withModel($context->resource->update($context->model, $context));

        $this->saveFields($context);

        $context = $this->runSavedCallbacks($context);

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
                "/$type/{id}" => [
                    'patch' => $this->mergeSchema([
                        'tags' => [$type],
                        'parameters' => $this->openApiResourceParameters(
                            $context,
                            $this->getParameters(),
                        ),
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                JsonApi::MEDIA_TYPE => [
                                    'schema' => $this->resourceDocumentSchema(
                                        $context,
                                        $this->openApiSchemaRefs(
                                            $context->collection->resources(),
                                            '_update',
                                        ),
                                    ),
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Resource updated successfully.',
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

    public function resourceLinks(SchemaContext $context): array
    {
        return [$this->resourceSelfLinkDefinition()];
    }
}
