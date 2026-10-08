<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Extension\Hook\HookParameters;
use Tobyz\JsonApiServer\Schema\Parameter;

trait HasParameters
{
    /**
     * @var (Parameter|HookParameters)[]
     */
    protected array $parameters = [];

    /**
     * Set custom parameters for the request.
     *
     * @param (Parameter|HookParameters)[] $parameters
     */
    public function parameters(array $parameters): static
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }
}
