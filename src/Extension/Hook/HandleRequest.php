<?php

namespace Tobyz\JsonApiServer\Extension\Hook;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\JsonApi;

/**
 * Handle a request instead of the API's endpoints.
 *
 * The callback receives the context and returns a response, or null to let the
 * request be handled normally. The extension is activated when it returns a
 * response, and a response without a Content-Type is given the JSON:API media
 * type.
 */
final class HandleRequest extends Hook
{
    public function __invoke(Context $context): ?ResponseInterface
    {
        $response = ($this->callback)($context);

        if ($response && !$response->hasHeader('Content-Type')) {
            $response = $response->withHeader('Content-Type', JsonApi::MEDIA_TYPE);
        }

        return $response;
    }
}
