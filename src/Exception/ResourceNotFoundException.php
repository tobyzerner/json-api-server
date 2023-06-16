<?php

namespace Tobyz\JsonApiServer\Exception;

use RuntimeException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class ResourceNotFoundException extends RuntimeException implements ErrorProviderInterface
{
    public function __construct(public readonly string $type, public readonly ?string $id = null)
    {
        parent::__construct(
            sprintf('Resource [%s] not found.', $type . ($id !== null ? '.' . $id : '')),
        );
    }

    public function getJsonApiErrors(): array
    {
        return [
            [
                'title' => 'Resource Not Found',
                'status' => $this->getJsonApiStatus(),
                'detail' => $this->getMessage(),
            ],
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '404';
    }
}
