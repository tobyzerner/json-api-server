<?php

namespace Tobyz\JsonApiServer\Laravel\Filter;

use Closure;

trait UsesRelationship
{
    public ?string $relationship = null;
    public ?Closure $scope = null;

    public function relationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function scope(?Closure $scope): static
    {
        $this->scope = $scope;

        return $this;
    }
}
