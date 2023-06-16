<?php

namespace Tobyz\JsonApiServer\Resource;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Attribute;
use Tobyz\JsonApiServer\Schema\Field\Field;

interface ResourceInterface
{
    /**
     * Get the resource type.
     */
    public function type(): string;

    /**
     * The resource's endpoints.
     */
    public function endpoints(): array;

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
     * Get the ID for a model.
     */
    public function getId(object $model, Context $context): string;

    /**
     * Get the value of a field for a model.
     */
    public function getValue(object $model, Field $field, Context $context): mixed;
}
