<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class EnumViolationException extends UnprocessableEntityException
{
    public function __construct(array $allowedValues, mixed $actual = null)
    {
        $formatted = array_map(fn($value) => '"' . $value . '"', $allowedValues);
        $message = sprintf('Value must be one of %s', implode(', ', $formatted));

        parent::__construct($message);

        $this->error['meta'] = ['allowed' => $allowedValues];

        if ($actual !== null) {
            $this->error['meta']['actual'] = $actual;
        }
    }
}
