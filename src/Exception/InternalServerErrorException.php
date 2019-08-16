<?php

namespace Tobyz\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use RuntimeException;
use Tobyz\JsonApiServer\ErrorProviderInterface;

class InternalServerErrorException extends RuntimeException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Internal Server Error'),
                new Error\Status($this->getJsonApiStatus())
            )
        ];
    }

    public function getJsonApiStatus(): string
    {
        return '500';
    }
}
