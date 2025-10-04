<?php

namespace Tobyz\JsonApiServer\Exception;

use DomainException;
use Tobyz\JsonApiServer\Exception\Concerns\JsonApiError;

class ResourceNotFoundException extends DomainException implements ErrorProvider, Sourceable
{
    use JsonApiError;

    public function __construct(public readonly string $type, public readonly ?string $id = null)
    {
        $identifier = $type . ($id !== null ? '.' . $id : '');

        parent::__construct(sprintf('Resource not found: %s', $identifier));

        $meta = ['type' => $this->type];

        if ($this->id !== null) {
            $meta['id'] = $this->id;
        }

        $this->error = ['meta' => $meta];
    }

    public function getJsonApiStatus(): string
    {
        return '404';
    }
}
