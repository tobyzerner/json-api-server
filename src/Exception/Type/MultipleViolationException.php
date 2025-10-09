<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\BadRequestException;

class MultipleViolationException extends BadRequestException
{
    public function __construct(int|float $multipleOf, int|float|null $actual = null)
    {
        parent::__construct(sprintf('Value must be a multiple of %s', $multipleOf));

        $this->error['meta'] = ['multipleOf' => $multipleOf];

        if ($actual !== null) {
            $this->error['meta']['actual'] = $actual;
        }
    }
}
