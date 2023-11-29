<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Type\Boolean;

class BooleanDateTime extends Attribute
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->type(Boolean::make());
    }

    public function setValue(mixed $model, mixed $value, Context $context): void
    {
        parent::setValue($model, $value ? new \DateTime() : null, $context);
    }

    public function saveValue(mixed $model, mixed $value, Context $context): void
    {
        parent::saveValue($model, $value ? new \DateTime() : null, $context);
    }
}
