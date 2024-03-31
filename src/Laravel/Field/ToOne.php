<?php

namespace Tobyz\JsonApiServer\Laravel\Field;

use Tobyz\JsonApiServer\Schema\Field\ToOne as BaseToOne;

class ToOne extends BaseToOne
{
    use Concerns\ScopesRelationship;
}
