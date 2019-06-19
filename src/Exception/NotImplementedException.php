<?php

namespace Tobscure\JsonApiServer\Exception;

use JsonApiPhp\JsonApi\Error;
use Tobscure\JsonApiServer\ErrorProviderInterface;

class NotImplementedException extends \DomainException implements ErrorProviderInterface
{
    public function getJsonApiErrors(): array
    {
        return [
            new Error(
                new Error\Title('Not Implemented'),
                new Error\Status('501')
            )
        ];
    }
}
