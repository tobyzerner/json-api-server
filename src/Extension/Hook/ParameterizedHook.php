<?php

namespace Tobyz\JsonApiServer\Extension\Hook;

use Tobyz\JsonApiServer\Schema\Parameter;

/**
 * A hook that can define parameters, which are loaded wherever it runs.
 */
abstract class ParameterizedHook extends Hook
{
    /**
     * @var Parameter[]
     */
    private array $parameters = [];

    /**
     * Define parameters that are loaded wherever this hook runs.
     *
     * @param Parameter[] $parameters
     */
    public function parameters(array $parameters): static
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * @return Parameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
