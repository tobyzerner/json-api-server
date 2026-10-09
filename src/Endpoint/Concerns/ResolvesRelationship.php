<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Exception\NotFoundException;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\RelatedListable;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\SchemaContext;

use function Tobyz\JsonApiServer\resolve_value;

trait ResolvesRelationship
{
    protected function resolveRelationshipField(
        Context $context,
        string $relationshipName,
    ): Relationship {
        $field = $context->fields($context->resource)[$relationshipName] ?? null;

        if (!$field instanceof Relationship) {
            throw new NotFoundException();
        }

        $context = $context->withField($field);

        if (!$field->isVisible($context)) {
            throw new NotFoundException();
        }

        return $field;
    }

    protected function resolveRelationshipData(Context $context, Relationship $field): mixed
    {
        if (
            ($collection = $this->listableRelationshipCollection(
                $field,
                $context,
            )) && ($query = $this->relatedQuery($field, $context->withCollection($collection)))
        ) {
            return $this->resolveList($query, $collection, $context, $field->pagination);
        }

        return $this->resolveRelationshipValue($context, $field);
    }

    protected function resolveRelationshipValue(Context $context, Relationship $field): mixed
    {
        // Related resource scopes see the declared relationship, not this copy,
        // so they can tell they're being loaded through it.
        return resolve_value(
            (clone $field)->withLinkage()->getValue($context->withField($field)),
        );
    }

    protected function listableRelationshipCollection(
        Relationship $field,
        SchemaContext $context,
    ): ?Listable {
        $collections = array_map($context->api->getCollection(...), $field->collections);

        if (
            $field instanceof ToMany
            && count($collections) === 1
            && $context->resource instanceof RelatedListable
            && $collections[0] instanceof Listable
        ) {
            return $collections[0];
        }

        return null;
    }

    protected function relatedQuery(ToMany $field, Context $context): ?object
    {
        return $context->resource->relatedQuery($context->model, $field, $context);
    }
}
