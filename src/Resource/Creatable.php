<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Field;

interface Creatable
{
    /**
     * Get a new model instance.
     */
    public function newModel(Context $context): object;

    /**
     * Set a field value on the model instance.
     */
    public function setValue(object $model, Field $field, mixed $value, Context $context): void;

    /**
     * Persist a field value on a model instance to storage.
     */
    public function saveValue(object $model, Field $field, mixed $value, Context $context): void;

    /**
     * Persist a new model instance to storage.
     */
    public function create(object $model, Context $context): object;
}
