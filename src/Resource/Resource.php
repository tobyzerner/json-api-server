<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Id;

interface Resource
{
    /**
     * Get the resource type.
     */
    public function type(): string;

    /**
     * Get the fields for this resource.
     *
     * @return Field[]
     */
    public function fields(): array;

    /**
     * Get the meta attributes for this resource.
     *
     * @return Attribute[]
     */
    public function meta(): array;

    /**
     * Get the ID schema for this resource.
     */
    public function id(): Id;

    /**
     * Get the value of a field for a model.
     */
    public function getValue(object $model, Field $field, Context $context): mixed;
}
