<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

use Tobyz\JsonApiServer\Schema\Type\Type;

trait HasType
{
    use AppliesType;

    public ?Type $type = null;

    public function type(?Type $type): static
    {
        $this->type = $type;

        return $this;
    }
}
