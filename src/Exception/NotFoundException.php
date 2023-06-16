<?php

namespace Tobyz\JsonApiServer\Exception;

use RuntimeException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class NotFoundException extends RuntimeException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => 'Not Found',
                'status' => $this->getJsonApiStatus(),
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '404';
    }
}
