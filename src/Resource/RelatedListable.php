<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\ToMany;

interface RelatedListable
{
    public function relatedQuery(object $model, ToMany $relationship, Context $context): ?object;
}
