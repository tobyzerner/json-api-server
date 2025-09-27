<?php

namespace Tobyz\JsonApiServer\Schema\Concerns;

trait HasSummary
{
    protected ?string $summary = null;

    /**
     * Set the summary of the field for documentation generation.
     */
    public function summary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }
}
