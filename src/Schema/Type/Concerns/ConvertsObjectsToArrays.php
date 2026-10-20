<?php

namespace Tobyz\JsonApiServer\Schema\Type\Concerns;

trait ConvertsObjectsToArrays
{
    /**
     * Read object values (e.g. from json_decode, collections, or model casts)
     * as arrays so that their contents can be serialized by nested types.
     * Without $preserveKeys, an iterator's keys are discarded so that
     * duplicate or non-scalar keys don't lose items or fail.
     */
    protected function objectToArray(mixed $value, bool $preserveKeys = true): mixed
    {
        if ($value instanceof \stdClass) {
            return (array) $value;
        }

        if ($value instanceof \Traversable) {
            return iterator_to_array($value, $preserveKeys);
        }

        if ($value instanceof \JsonSerializable) {
            $serialized = $value->jsonSerialize();

            return $serialized instanceof \stdClass ? (array) $serialized : $serialized;
        }

        return $value;
    }
}
