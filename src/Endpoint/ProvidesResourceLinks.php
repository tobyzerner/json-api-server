<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\SchemaContext;

interface ProvidesResourceLinks
{
    public function resourceLinks(SchemaContext $context): array;
}
