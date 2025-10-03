<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\SingleError;

class ResourceNotFoundException extends DomainException implements ErrorProvider, Sourceable
{
    use SingleError;

    public function __construct(
        public readonly string $type,
        public readonly ?string $id = null,
        ?string $detail = null,
    ) {
        $identifier = $type . ($id !== null ? '.' . $id : '');

        parent::__construct($detail ?? sprintf('Resource not found: %s', $identifier));
    }

    public function getJsonApiStatus(): string
    {
        return '404';
    }
}
