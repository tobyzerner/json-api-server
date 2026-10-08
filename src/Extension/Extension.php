<?php

namespace Tobyz\JsonApiServer\Extension;

use Tobyz\JsonApiServer\Extension\Hook\Hook;

abstract class Extension
{
    /**
     * The URI that uniquely identifies this extension.
     *
     * @see https://jsonapi.org/format/1.1/#media-type-parameter-rules
     */
    abstract public function uri(): string;

    /**
     * The namespace that prefixes members and query parameters introduced by this
     * extension, or null if it introduces none.
     *
     * @see https://jsonapi.org/format/1.1/#extension-rules
     */
    public function namespace(): ?string
    {
        return null;
    }

    /**
     * Get the hooks through which this extension changes how requests are handled.
     *
     * Called once when the extension is registered. Hooks only run for requests
     * that include this extension in the media type.
     *
     * @return Hook[]
     */
    public function hooks(): array
    {
        return [];
    }
}
