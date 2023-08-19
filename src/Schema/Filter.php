<?php

namespace Tobyz\JsonApiServer\Schema;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

abstract class Filter
{
    use HasDescription;
    use HasVisibility;

    public function __construct(public string $name)
    {
    }

    abstract public function apply(object $query, string|array $value, Context $context): void;
}
