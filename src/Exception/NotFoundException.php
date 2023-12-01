<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\SingleError;

class NotFoundException extends DomainException implements ErrorProviderInterface, Sourceable
{
    use SingleError;

    public function getJsonApiStatus(): string
    {
        return '404';
    }
}
