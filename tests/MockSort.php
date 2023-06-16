<?php

namespace Tobyz\Tests\JsonApiServer;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Sort;

class MockSort extends Sort
{
    public static function make(string $name): static
    {
        return new static($name);
    }

    public function apply(object $query, string $direction, Context $context): void
    {
        $query->sorts[] = [$this->name, $direction];
    }
}
