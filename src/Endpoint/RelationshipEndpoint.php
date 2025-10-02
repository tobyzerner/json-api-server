<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

interface RelationshipEndpoint
{
    public function relationshipLinks($model, Relationship $field, Context $context): array;
}
