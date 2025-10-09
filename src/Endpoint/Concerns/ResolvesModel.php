<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;

trait ResolvesModel
{
    use FindsResources;
    use HasVisibility;

    protected function resolveModel(Context $context, string $id): Context
    {
        $model = $this->findResource($context, $id);

        $context = $context->forModel([$context->collection], $model);

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        return $context;
    }

    protected function resourceSelfLink($model, Context $context): string
    {
        return implode('/', [
            $context->api->basePath,
            $context->collection->name(),
            $context->id($context->resource, $model),
        ]);
    }
}
