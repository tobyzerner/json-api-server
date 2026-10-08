<?php

namespace Tobyz\JsonApiServer\Extension\Hook;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Resource\Resource;

/**
 * Resolve the sparse fieldset for a resource.
 *
 * The callback receives the resource and context, and returns the names of the
 * fields to serialize, or null to defer to other extensions and the `fields`
 * parameter. Unknown names are ignored. The extension is activated when it
 * returns a fieldset.
 */
final class SparseFields extends ParameterizedHook
{
    /**
     * @return string[]|null
     */
    public function __invoke(Resource $resource, Context $context): ?array
    {
        return ($this->callback)($resource, $context);
    }
}
