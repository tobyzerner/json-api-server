<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class RangeViolationException extends UnprocessableEntityException
{
    public function __construct(
        string $constraint,
        int|float $limit,
        int|float|null $actual = null,
        bool $exclusive = false,
    ) {
        parent::__construct('Value is out of range');

        $this->error['meta'] = [
            'constraint' => $constraint,
            'limit' => $limit,
        ];

        if ($actual !== null) {
            $this->error['meta']['actual'] = $actual;
        }

        if ($exclusive) {
            $this->error['meta']['exclusive'] = true;
        }
    }
}
