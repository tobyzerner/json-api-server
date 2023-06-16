<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

trait HasDescription
{
    protected ?string $description = null;

    /**
     * Set the description of the field for documentation generation.
     */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
