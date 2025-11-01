<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;

trait EndpointDispatcher
{
    private function dispatchEndpoints(iterable $endpoints, Context $context): ?ResponseInterface
    {
        $methodNotAllowed = null;

        foreach ($endpoints as $endpoint) {
            try {
                if ($response = $endpoint->handle($context->withEndpoint($endpoint))) {
                    return $response;
                }
            } catch (MethodNotAllowedException $exception) {
                $methodNotAllowed ??= $exception;
            }
        }

        if ($methodNotAllowed) {
            throw $methodNotAllowed;
        }

        return null;
    }
}
