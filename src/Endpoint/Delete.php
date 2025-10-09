<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesDocument;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Resource\Deletable;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\SchemaContext;

class Delete implements Endpoint, ProvidesRootSchema, ProvidesResourceLinks
{
    use HasResponse;
    use HasSchema;
    use ResolvesModel;
    use SerializesDocument;

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

        if ($context->method() !== 'DELETE') {
            throw new MethodNotAllowedException();
        }

        $context = $this->resolveModel($context, $segments[0]);

        if (!$context->resource instanceof Deletable) {
            throw new RuntimeException(
                sprintf('%s must implement %s', get_class($context->resource), Deletable::class),
            );
        }

        $context->resource->delete($context->model, $context);

        return $this->createResponse($this->serializeDocument($context), $context)->withStatus(204);
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();

        return [
            'paths' => [
                "/$type/{id}" => [
                    'delete' => [
                        'tags' => [$type],
                        'parameters' => [
                            [
                                'name' => 'id',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'string'],
                            ],
                        ],
                        'responses' => [
                            '204' => [
                                'description' => 'Resource deleted successfully.',
                                ...$this->responseSchema(null, $context),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function resourceLinks(SchemaContext $context): array
    {
        return [
            Link::make('self')->get(
                fn($model, Context $context) => $this->resourceSelfLink($model, $context),
            ),
        ];
    }
}
