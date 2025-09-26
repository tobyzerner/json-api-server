<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use InvalidArgumentException;

trait SupportsOperators
{
    protected array $operators = self::SUPPORTED_OPERATORS;

    public function operators(array $only): static
    {
        $invalid = array_diff($only, static::SUPPORTED_OPERATORS);

        if (!empty($invalid)) {
            throw new InvalidArgumentException(
                'Unsupported operators requested: ' . implode(', ', $invalid),
            );
        }

        $this->operators = $only;

        return $this;
    }

    protected function resolveOperators(array|string $value): array
    {
        $default = $this->operators[0];

        if (is_string($value) || array_is_list($value)) {
            return [$default => $value];
        }

        $result = [];

        foreach ($value as $key => $val) {
            if (in_array($key, $this->operators)) {
                $result[$key] = $val;
            } elseif (is_array($result[$default] ?? [])) {
                $result[$default][$key] = $val;
            } else {
                $result[$default] = [$result[$default], $val];
            }
        }

        return $result;
    }
}
