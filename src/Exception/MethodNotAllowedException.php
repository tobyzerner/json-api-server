<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException as DomainExceptionAlias;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class MethodNotAllowedException extends DomainExceptionAlias implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => 'Method Not Allowed',
                'status' => $this->getJsonApiStatus(),
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '405';
    }
}
