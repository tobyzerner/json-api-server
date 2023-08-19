<?php

namespace Tobyz\JsonApiServer\Laravel;

use Tobyz\JsonApiServer\Context;

trait SoftDeletes
{
    public function delete(object $model, Context $context): void
    {
        $model->forceDelete();
    }
}
