<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Serializer;

trait BuildsRelationshipDocument
{
    use BuildsDocument;

    private function buildRelationshipDocument(
        Relationship $field,
        mixed $data,
        Context $context,
    ): array {
        $document = (clone $field)
            ->withLinkage()
            ->serializeValue($data, $context->withSerializer(new Serializer()));

        return array_replace_recursive($document, $this->buildDocument($context));
    }
}
