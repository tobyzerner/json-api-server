<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Schema\Concerns\HasType;
use Tobyz\JsonApiServer\Schema\Field\Field;

class Meta extends Field
{
    use HasType;

    public static function make(string $name): static
    {
        return new static($name);
    }
}
