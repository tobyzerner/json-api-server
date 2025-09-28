<?php

namespace Tobyz\JsonApiServer\Pagination;

use Tobyz\JsonApiServer\Context;

interface Pagination
{
    public function paginate(object $query, Context $context): array;
}
