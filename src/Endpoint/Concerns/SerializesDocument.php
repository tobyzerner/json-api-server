<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\ProvidesDocumentLinks;
use Tobyz\JsonApiServer\Endpoint\ProvidesDocumentMeta;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Tobyz\JsonApiServer\SchemaContext;

trait SerializesDocument
{
    use HasMeta;

    private function serializeDocument(Context $context): array
    {
        $document = [];

        foreach ($this->serializeMeta($context) as $key => $value) {
            $document['meta'][$key] = $value;
        }

        return $document;
    }

    private function documentSchema(SchemaContext $context, array $schemaProviders = []): array
    {
        $meta = [];
        $links = [];

        foreach ($schemaProviders as $provider) {
            if ($provider instanceof ProvidesDocumentMeta) {
                foreach ($provider->documentMeta() as $m) {
                    $meta[$m->name] = (object) $m->getSchema($context);
                }
            }

            if ($provider instanceof ProvidesDocumentLinks) {
                foreach ($provider->documentLinks() as $link) {
                    $links[$link->name] = (object) $link->getSchema($context);
                }
            }
        }

        foreach ($this->meta as $m) {
            $meta[$m->name] = (object) $m->getSchema($context);
        }

        return [
            'type' => 'object',
            'properties' => [
                ...$meta ? ['meta' => ['type' => 'object', 'properties' => $meta]] : [],
                ...$links ? ['links' => ['type' => 'object', 'properties' => $links]] : [],
            ],
        ];
    }
}
