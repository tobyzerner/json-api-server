<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Schema\Parameter;

trait HasParameters
{
    /**
     * @var Parameter[]
     */
    protected array $parameters = [];

    /**
     * Set custom parameters for the request.
     *
     * @param Parameter[] $parameters
     */
    public function parameters(array $parameters): static
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }
}
