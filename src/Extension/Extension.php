<?php

namespace Tobyz\JsonApiServer\Extension;

use Psr\Http\Message\ResponseInterface as Response;
use Tobyz\JsonApiServer\Context;

abstract class Extension
{
    /**
     * The URI that uniquely identifies this extension.
     *
     * @see https://jsonapi.org/format/1.1/#media-type-parameter-rules
     */
    abstract public function uri(): string;

    /**
     * Handle a request.
     */
    public function handle(Context $context): ?Response
    {
        return null;
    }
}
