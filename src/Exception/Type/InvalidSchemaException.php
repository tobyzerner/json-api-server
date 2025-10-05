<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class InvalidSchemaException extends UnprocessableEntityException
{
    public function __construct(string $constraint, ?int $matched = null)
    {
        parent::__construct('Value does not match schema constraint');

        $this->error['meta'] = ['constraint' => $constraint];

        if ($matched !== null) {
            $this->error['meta']['matched'] = $matched;
        }
    }
}
