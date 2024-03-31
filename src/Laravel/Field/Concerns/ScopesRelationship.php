<?php

namespace Tobyz\JsonApiServer\Laravel\Field\Concerns;

use Closure;

trait ScopesRelationship
{
    public ?Closure $scope = null;

    public function scope(?Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }
}
