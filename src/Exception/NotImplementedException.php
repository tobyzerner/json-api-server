<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\JsonApiError;

class NotImplementedException extends DomainException implements ErrorProvider, Sourceable
{
    use JsonApiError;

    public function getJsonApiStatus(): string
    {
        return '501';
    }
}
