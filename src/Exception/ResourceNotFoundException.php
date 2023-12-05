<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\SingleError;

class ResourceNotFoundException extends DomainException implements ErrorProvider, Sourceable
{
    use SingleError;

    public function __construct(public readonly string $type, public readonly ?string $id = null)
    {
        parent::__construct(
            sprintf('Resource [%s] not found.', $type . ($id !== null ? '.' . $id : '')),
        );
    }

    public function getJsonApiStatus(): string
    {
        return '404';
    }
}
