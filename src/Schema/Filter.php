<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

abstract class Filter
{
    use HasSchema;
    use HasVisibility;

    public function __construct(public string $name)
    {
    }

    abstract public function apply(object $query, string|array $value, Context $context): void;
}
