<?php

namespace Tobyz\JsonApiServer\Endpoint\Concerns;

use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\ProvidesResourceMeta;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\Schema\Type;
use Tobyz\JsonApiServer\SchemaContext;
use Tobyz\JsonApiServer\Serializer;

trait SerializesResourceDocument
{
    use IncludesData;
    use SerializesDocument;

    protected function resourceDocumentParameters(): array
    {
        return [
            Parameter::make('include')
                ->in('query')
                ->description('Comma-separated list of relationship paths to include')
                ->type(Type\Str::make())
                ->default($this->defaultInclude ? implode(',', $this->defaultInclude) : null),

            Parameter::make('fields')
                ->in('query')
                ->description('Comma-separated sparse fieldsets keyed by type')
                ->type(Type\Obj::make()->additionalProperties(Type\Str::make())),
        ];
    }

    private function serializeResourceDocument(
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

        return $document + $this->serializeDocument($context);
    }

    private function resourceDocumentSchema(
        SchemaContext $context,
        array $resourceSchemas,
        bool $multiple = false,
        array $schemaProviders = [],
    ): array {
        $resourceMeta = [];

        foreach ($schemaProviders as $provider) {
            if ($provider instanceof ProvidesResourceMeta) {
                foreach ($provider->resourceMeta() as $meta) {
                    $resourceMeta[$meta->name] = $meta->getSchema($context);
                }
            }
        }

        if ($resourceMeta) {
            $resourceSchemas = array_map(
                fn($schema) => ['allOf' => [$schema, ['meta' => $resourceMeta]]],
                $resourceSchemas,
            );
        }

        $item = count($resourceSchemas) === 1 ? $resourceSchemas[0] : ['oneOf' => $resourceSchemas];

        return array_replace_recursive($this->documentSchema($context, $schemaProviders), [
            'type' => 'object',
            'required' => ['data'],
            'properties' => [
                'data' => $multiple ? ['type' => 'array', 'items' => $item] : $item,
                'included' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/jsonApiResource'],
                ],
            ],
        ]);
    }
}
