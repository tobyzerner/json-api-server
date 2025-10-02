<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;

trait ShowsResources
{
    public function resourceLinks($model, Context $context): array
    {
        return ['self' => $this->selfLink($model, $context)];
    }

    private function selfLink($model, Context $context): string
    {
        return implode('/', [
            $context->api->basePath,
            $context->collection->name(),
            $context->resource->getId($model, $context),
        ]);
    }
}
