<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;

interface Deletable extends Findable
{
    /**
     * Delete a model.
     */
    public function delete(object $model, Context $context): void;
}
