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

        foreach ($this->serializeMeta($context) as $key => $value) {
            $document['meta'][$key] = $value;
        }

        return $document;
    }
}
