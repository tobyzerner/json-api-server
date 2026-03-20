<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Schema\Concerns\HasType;

class Attribute extends Field
{
    use HasType;

    public static function make(string $name): static
    {
        return new static($name);
    }

    public static function location(): ?string
    {
        return 'attributes';
    }
}
