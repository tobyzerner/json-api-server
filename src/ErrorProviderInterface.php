<?php

namespace Tobyz\JsonApiServer;

use JsonApiPhp\JsonApi\Error;

interface ErrorProviderInterface
{
    /**
     * Get JSON:API error objects that represent this error.
     *
     * @return Error[]
     */
    public function getJsonApiErrors(): array;

    /**
     * Get the most generally applicable HTTP error code for this error.
     */
    public function getJsonApiStatus(): string;
}
