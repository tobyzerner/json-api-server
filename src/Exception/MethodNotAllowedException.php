<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException as DomainExceptionAlias;
use JsonApiPhp\JsonApi\Error;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class MethodNotAllowedException extends DomainExceptionAlias implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Method Not Allowed'),
                new Error\Status($this->getJsonApiStatus())
            )
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '405';
    }
}
