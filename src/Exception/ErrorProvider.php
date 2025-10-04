<?php

namespace Tobyz\JsonApiServer\Exception;

interface ErrorProvider
{
    /**
     * Get a JSON:API error object that represents this error.
     */
    public function getJsonApiError(): array;

    /**
     * Get the most generally applicable HTTP error code for this error.
     */
    public function getJsonApiStatus(): string;
}
