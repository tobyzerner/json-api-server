<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Tobyz\JsonApiServer\Exception\BadRequestException;

trait SupportsOperators
{
    private function resolveOperator(array|string $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            return ['eq', $value];
        }

        $keys = array_keys($value);

        if (count($keys) !== 1) {
            throw new BadRequestException('Operator groups cannot combine with other values');
        }

        $operator = $keys[0];

        return [$operator, $value[$operator]];
    }
}
