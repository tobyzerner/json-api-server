<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\EndpointDispatcher;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\SchemaContext;

abstract class AggregateEndpoint implements Endpoint, ProvidesRootSchema
{
    use EndpointDispatcher;

    abstract public function endpoints(): array;

    public function handle(Context $context): ?ResponseInterface
    {
        return $this->dispatchEndpoints($this->endpoints(), $context);
    }

    public function rootSchema(SchemaContext $context): array
    {
        $schema = [];

        foreach ($this->endpoints() as $endpoint) {
            if ($endpoint instanceof ProvidesRootSchema) {
                $schema = array_replace_recursive($schema, $endpoint->rootSchema($context));
            }
        }

        return $schema;
    }
}
