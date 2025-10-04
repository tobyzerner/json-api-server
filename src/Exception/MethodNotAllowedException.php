<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\JsonApiError;

class MethodNotAllowedException extends DomainException implements ErrorProvider, Sourceable
{
    use JsonApiError;

    public function __construct()
    {
        parent::__construct('Method not allowed');
    }

    public function getJsonApiStatus(): string
    {
        return '405';
    }
}
