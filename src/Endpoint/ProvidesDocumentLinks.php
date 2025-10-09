<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Schema\Link;

interface ProvidesDocumentLinks
{
    /**
     * @return Link[]
     */
    public function documentLinks(): array;
}
