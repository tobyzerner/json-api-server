<?php

namespace Tobscure\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use Tobscure\JsonApiServer\ErrorProviderInterface;

class BadRequestException extends \DomainException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Forbidden'),
                new Error\Status('403')
            )
        ];
    }
}
