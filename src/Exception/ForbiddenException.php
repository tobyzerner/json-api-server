<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class ForbiddenException extends DomainException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => 'Forbidden',
                'status' => $this->getJsonApiStatus(),
                ...$this->message ? ['detail' => $this->message] : [],
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '403';
    }
}
