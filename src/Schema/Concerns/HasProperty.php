<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

trait HasProperty
{
    public ?string $property = null;

    public function property(?string $property): static
    {
        $this->property = $property;

        return $this;
    }
}
