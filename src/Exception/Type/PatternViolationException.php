<?php

namespace Tobyz\JsonApiServer\Exception\Type;

use Tobyz\JsonApiServer\Exception\UnprocessableEntityException;

class PatternViolationException extends UnprocessableEntityException
{
    public function __construct(string $pattern)
    {
        parent::__construct(sprintf('Value must match the pattern %s', $pattern));

        $this->error['meta'] = ['pattern' => $pattern];
    }
}
