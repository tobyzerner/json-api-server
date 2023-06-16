<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class ConflictException extends DomainException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => 'Conflict',
                'status' => $this->getJsonApiStatus(),
                ...$this->message ? ['detail' => $this->message] : [],
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '409';
    }
}
