<?php

namespace Tobyz\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use RuntimeException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class UnsupportedMediaTypeException extends RuntimeException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Unsupported Media Type'),
                new Error\Status($this->getJsonApiStatus())
            )
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '415';
    }
}
