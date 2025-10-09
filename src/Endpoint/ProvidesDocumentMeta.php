<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Schema\Meta;

interface ProvidesDocumentMeta
{
    /**
     * @return Meta[]
     */
    public function documentMeta(): array;
}
