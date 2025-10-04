<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\JsonApiError;

class ForbiddenException extends DomainException implements ErrorProvider, Sourceable
{
    use JsonApiError;

    public function getJsonApiStatus(): string
    {
        return '403';
    }
}
