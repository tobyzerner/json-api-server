<?php

namespace Tobyz\JsonApiServer\Extension;

use Psr\Http\Message\ResponseInterface as Response;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\ProvidesParameters;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Parameter;

abstract class Extension implements ProvidesParameters
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
     * Get the query and header parameters this extension accepts.
     *
     * They are loaded and validated alongside endpoint parameters for requests
     * that include this extension in the media type, and documented in the
     * OpenAPI definition. Query parameter names must be prefixed with the
     * extension's namespace.
     *
     * @return Parameter[]
     */
    public function parameters(): array
    {
        return [];
    }

    /**
     * Resolve the sparse fieldset for a resource.
     *
     * Return the names of the fields to serialize, or null to defer to other
     * extensions and the `fields` parameter. Unknown names are ignored. The
     * extension is activated when it returns a fieldset.
     *
     * @return string[]|null
     */
    public function sparseFields(Resource $resource, Context $context): ?array
    {
        return null;
    }

    /**
     * Handle a request.
     *
     * A response without a Content-Type is given the JSON:API media type.
     */
    public function handle(Context $context): ?Response
    {
        return null;
    }
}
