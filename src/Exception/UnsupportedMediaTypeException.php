<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\SingleError;

class UnsupportedMediaTypeException extends DomainException implements ErrorProvider, Sourceable
{
    use SingleError;

    public function getJsonApiStatus(): string
    {
        return '415';
    }
}
