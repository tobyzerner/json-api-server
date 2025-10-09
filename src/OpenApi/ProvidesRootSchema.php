<?php

namespace Tobyz\JsonApiServer\OpenApi;

use Tobyz\JsonApiServer\SchemaContext;

interface ProvidesRootSchema
{
    public function rootSchema(SchemaContext $context): array;
}
