<?php

namespace Tobyz\JsonApiServer\Schema\Field;

use Tobyz\JsonApiServer\Schema\Concerns\GetsValue;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Concerns\SetsValue;

abstract class Field
{
    use HasVisibility;
    use GetsValue;
    use SetsValue;

    public ?string $property = null;
    public bool $nullable = false;

    public function __construct(public readonly string $name)
    {
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function property(?string $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }
}
