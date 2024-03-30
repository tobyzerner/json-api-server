<?php

namespace Tobyz\JsonApiServer\Laravel\Field;

use Tobyz\JsonApiServer\Schema\Field\ToMany as BaseToMany;

class ToMany extends BaseToMany
{
    use Concerns\ScopesRelationship;
}
