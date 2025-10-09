<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\SchemaContext;

interface ProvidesRelationshipLinks
{
    /**
     * @return Link[]
     */
    public function relationshipLinks(Relationship $field, SchemaContext $context): array;
}
