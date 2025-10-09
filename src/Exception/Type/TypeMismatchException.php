<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class TypeMismatchException extends BadRequestException
{
    public function __construct(string $expected, ?string $actual = null)
    {
        parent::__construct("Value must be $expected");

        if ($actual !== null) {
            $this->error['meta'] = [
                'expected' => $expected,
                'actual' => $actual,
            ];
        }
    }
}
