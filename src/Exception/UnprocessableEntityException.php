<?php

namespace Tobscure\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use Tobscure\JsonApiServer\ErrorProviderInterface;

class UnprocessableEntityException extends \DomainException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Unprocessable Entity'),
                new Error\Status('422')
            )
        ];
    }
}
