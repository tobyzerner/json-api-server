<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Context;

class BooleanDateTime extends Boolean
{
    public function setValue(mixed $model, mixed $value, Context $context): void
    {
        parent::setValue($model, $value ? new \DateTime() : null, $context);
    }

    public function saveValue(mixed $model, mixed $value, Context $context): void
    {
        parent::saveValue($model, $value ? new \DateTime() : null, $context);
    }
}
