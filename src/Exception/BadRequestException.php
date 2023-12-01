<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\SingleError;

class BadRequestException extends DomainException implements ErrorProviderInterface, Sourceable
{
    use SingleError;

    public function getJsonApiStatus(): string
    {
        return '400';
    }
}
