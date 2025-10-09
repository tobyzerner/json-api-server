<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\SchemaContext;
use Tobyz\JsonApiServer\Serializer;

trait SerializesRelationshipDocument
{
    use SerializesDocument;

    private function serializeRelationshipDocument(
        Relationship $field,
        mixed $data,
        Context $context,
    ): array {
        $document = (clone $field)
            ->withLinkage()
            ->serializeValue($data, $context->withSerializer(new Serializer()));

        return array_replace_recursive($document, $this->serializeDocument($context));
    }

    private function relationshipDocumentSchema(
        SchemaContext $context,
        array $relationshipSchema,
        bool $multiple = false,
    ): array {
        return array_replace_recursive($this->documentSchema($context), [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => $multiple
                    ? ['type' => 'array', 'items' => $relationshipSchema]
                    : $relationshipSchema,
            ],
        ]);
    }
}
