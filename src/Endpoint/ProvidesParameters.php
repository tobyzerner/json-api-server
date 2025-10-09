<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Schema\Parameter;

interface ProvidesParameters
{
    /**
     * @return Parameter[]
     */
    public function parameters(): array;
}
