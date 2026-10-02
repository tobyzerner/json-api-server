<?php

namespace Tobyz\JsonApiServer\Laravel\Field;

use Tobyz\JsonApiServer\Schema\Field\Attribute as BaseAttribute;

class Attribute extends BaseAttribute
{
    use Concerns\LoadsRelations;
}
