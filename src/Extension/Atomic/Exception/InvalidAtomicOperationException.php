<?php

namespace Tobyz\JsonApiServer\Extension\Atomic\Exception;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class InvalidAtomicOperationException extends BadRequestException
{
    public function __construct(public readonly ?string $operation)
    {
        parent::__construct('Invalid operation: ' . ($operation ?? 'null'));

        $this->meta(['operation' => $this->operation]);
    }
}
