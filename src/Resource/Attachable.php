<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;

interface Attachable
{
    /**
     * Attach a related model to a model.
     */
    public function attach(
        object $model,
        Relationship $relationship,
        mixed $related,
        Context $context,
    ): void;

    /**
     * Detach a related model from a model.
     */
    public function detach(
        object $model,
        Relationship $relationship,
        mixed $related,
        Context $context,
    ): void;
}
