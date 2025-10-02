<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

interface Attachable
{
    /**
     * Attach related models to a model.
     */
    public function attach(
        object $model,
        Relationship $relationship,
        array $related,
        Context $context,
    ): void;

    /**
     * Detach related models from a model.
     */
    public function detach(
        object $model,
        Relationship $relationship,
        array $related,
        Context $context,
    ): void;
}
