<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use DomainException;
use Tobyz\JsonApiServer\Schema\Concerns\AppliesType;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Type\Str;

class Id extends Field
{
    use AppliesType;

    public Str $type;

    public function __construct()
    {
        parent::__construct('id');

        $this->type(Str::make());
        $this->nullable = false;
    }

    public static function make(): static
    {
        return new static();
    }

    public function type(Str $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function writable(?Closure $condition = null): static
    {
        throw new DomainException('ID cannot be writable. Use writeableOnCreate() instead.');
    }

    public function nullable(bool $nullable = true): static
    {
        throw new DomainException('ID cannot be nullable.');
    }
}
