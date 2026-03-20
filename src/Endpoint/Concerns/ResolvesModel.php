<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Link;

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

    protected function relationshipSelfLink($model, Relationship $field, Context $context): string
    {
        return $this->resourceSelfLink($model, $context) . '/relationships/' . $field->name;
    }

    protected function relatedLink($model, Relationship $field, Context $context): string
    {
        return $this->resourceSelfLink($model, $context) . '/' . $field->name;
    }

    protected function resourceSelfLinkDefinition(): Link
    {
        return Link::make('self')->get(
            fn($model, Context $context) => $this->resourceSelfLink($model, $context),
        );
    }

    protected function relationshipSelfLinkDefinition(Relationship $field): Link
    {
        return Link::make('self')->get(
            fn($model, Context $context) => $this->relationshipSelfLink($model, $field, $context),
        );
    }

    protected function relatedLinkDefinition(Relationship $field): Link
    {
        return Link::make('related')->get(
            fn($model, Context $context) => $this->relatedLink($model, $field, $context),
        );
    }
}
