<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesDocument;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\SchemaContext;

class CollectionAction implements Endpoint, ProvidesRootSchema
{
    use HasVisibility;
    use HasResponse;
    use HasSchema;
    use SerializesDocument;

    private string $method = 'POST';

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
        $segments = $context->pathSegments();

        if (count($segments) !== 1 || $segments[0] !== $this->name) {
            return null;
        }

        if (strtoupper($context->method()) !== $this->method) {
            throw new MethodNotAllowedException();
        }

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        if ($response = ($this->handler)($context)) {
            return $this->applyResponseHooks($response, $context);
        }

        return $this->createResponse($this->serializeDocument($context), $context)->withStatus(204);
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();

        return [
            'paths' => [
                "/$type/$this->name" => [
                    strtolower($this->method) => $this->mergeSchema([
                        'tags' => [$type],
                        'responses' => [
                            '204' => [
                                'description' => 'Action performed successfully.',
                                ...$this->responseSchema(null, $context),
                            ],
                        ],
                    ]),
                ],
            ],
        ];
    }
}
