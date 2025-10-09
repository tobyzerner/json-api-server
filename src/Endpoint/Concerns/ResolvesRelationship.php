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
            ($collection = $this->listableRelationshipCollection($field, $context)) &&
            ($query = $this->relatedQuery($field, $context))
        ) {
            $relatedData = $this->resolveList($query, $collection, $context, $field->pagination);
        } else {
            $relatedData = resolve_value($field->getValue($context->withInclude([])));
        }

        return $relatedData;
    }

    protected function listableRelationshipCollection(
        Relationship $field,
        SchemaContext $context,
    ): ?Listable {
        $collections = array_map($context->api->getCollection(...), $field->collections);

        if (
            $field instanceof ToMany &&
            count($collections) === 1 &&
            $context->resource instanceof RelatedListable &&
            $collections[0] instanceof Listable
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
