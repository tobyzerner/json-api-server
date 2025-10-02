<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Serializer;

trait BuildsResourceDocument
{
    use BuildsDocument;
    use IncludesData;

    private function buildResourceDocument(
        mixed $data,
        Context $context,
        array $collections = null,
    ): array {
        $collections ??= [$context->collection];

        $serializer = new Serializer();

        $include = $this->getInclude($context, $collections);

        $models = is_array($data) ? $data : ($data ? [$data] : []);

        foreach ($models as $model) {
            $serializer->addPrimary(
                $context->forModel($collections, $model)->withInclude($include),
            );
        }

        [$primary, $included] = $serializer->serialize();

        $document = ['data' => is_array($data) ? $primary : $primary[0] ?? null];

        if ($included) {
            $document['included'] = $included;
        }

        return $document + $this->buildDocument($context);
    }
}
