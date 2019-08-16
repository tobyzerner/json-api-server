<?php

namespace Tobyz\JsonApiServer\Schema;

use Closure;
use function Tobyz\JsonApiServer\wrap;

final class Meta
{
    private $name;
    private $value;

    public function __construct(string $name, $value)
    {
        $this->name = $name;
        $this->value = wrap($value);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): Closure
    {
        return $this->value;
    }
}
