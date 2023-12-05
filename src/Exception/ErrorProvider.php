<?php

namespace Tobyz\JsonApiServer\Exception;

interface ErrorProvider
{
    /**
     * Get JSON:API error objects that represent this error.
     */
    public function getJsonApiErrors(): array;

    /**
     * Get the most generally applicable HTTP error code for this error.
     */
    public function getJsonApiStatus(): string;
}
