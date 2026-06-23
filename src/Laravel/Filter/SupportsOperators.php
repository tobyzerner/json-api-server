<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use InvalidArgumentException;
use Tobyz\JsonApiServer\Schema\Type;

trait SupportsOperators
{
    public function operators(array $only): static
    {
        [$operators] = $this->parseOperators($only);

        $invalid = array_diff($operators, static::SUPPORTED_OPERATORS);

        if (!empty($invalid)) {
            throw new InvalidArgumentException(
                'Unsupported operators requested: ' . implode(', ', $invalid),
            );
        }

        return parent::operators($only);
    }

    protected function operatorPayloadType(string $operator): ?Type\Type
    {
        if (array_key_exists($operator, $this->operatorTypes)) {
            return parent::operatorPayloadType($operator);
        }

        $type =
            [
                'null' => Type\Boolean::make(),
                'notnull' => Type\Boolean::make(),
            ][$operator] ?? parent::operatorPayloadType($operator);
        
        $listOperator = in_array($operator, ['eq', 'ne', 'in', 'notin'], true);

        if (!$listOperator && $type instanceof Type\Arr) {
            return $type->items ?? Type\Any::make();
        }

        if (!$listOperator || !$type || $type instanceof Type\Arr) {
            return $type;
        }

        return Type\OneOf::make([$type, Type\Arr::make()->items($type)]);
    }
}
