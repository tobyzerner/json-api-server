<?php

namespace Tobyz\JsonApiServer\Exception;

use RuntimeException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class NotAcceptableException extends RuntimeException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => 'Not Acceptable',
                'status' => $this->getJsonApiStatus(),
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '406';
    }
}
