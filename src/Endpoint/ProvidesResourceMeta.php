<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Schema\Meta;

interface ProvidesResourceMeta
{
    /**
     * @return Meta[]
     */
    public function resourceMeta(): array;
}
