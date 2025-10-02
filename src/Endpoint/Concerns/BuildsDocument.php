<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

trait BuildsDocument
{
    use HasMeta;

    private function buildDocument(Context $context): array
    {
        $document = [];

        if ($meta = $context->documentMeta->getArrayCopy()) {
            $document['meta'] = $meta;
        }

        foreach ($this->serializeMeta($context) as $key => $value) {
            $document['meta'][$key] = $value;
        }

        if ($links = $context->documentLinks->getArrayCopy()) {
            $document['links'] = $links;
        }

        return $document;
    }
}
