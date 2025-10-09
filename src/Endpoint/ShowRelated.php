<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesList;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesRelationship;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesResourceDocument;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Resource\Findable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

class ShowRelated implements Endpoint, ProvidesRootSchema, ProvidesRelationshipLinks
{
    use HasParameters;
    use HasResponse;
    use ResolvesModel;
    use ResolvesRelationship;
    use ResolvesList;
    use SerializesResourceDocument;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = $context->pathSegments();

        if (count($segments) !== 2) {
            return null;
        }

        if ($context->method() !== 'GET') {
            throw new MethodNotAllowedException();
        }

        [$id, $relationshipName] = $segments;

        $context = $this->resolveModel($context, $id);

        $field = $this->resolveRelationshipField($context, $relationshipName);

        if (!$this->hasRelatedLink($field, $context)) {
            return null;
        }

        $context = $context->withParameters($this->getParameters($field, $context));

        $relatedData = $this->resolveRelationshipData($context, $field);

        $relatedCollections = array_map($context->api->getCollection(...), $field->collections);

        $document = $this->serializeResourceDocument($relatedData, $context, $relatedCollections);

        $document['links']['self'] ??= $this->relatedLink($context->model, $field, $context);

        return $this->createResponse($document, $context);
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();
        $paths = [];

        foreach ($context->collection->resources() as $resourceName) {
            $resource = $context->api->getResource($resourceName);
            $context = $context->withResource($resource);

            foreach ($resource->fields() as $field) {
                if (!$field instanceof Relationship || !$this->hasRelatedLink($field, $context)) {
                    continue;
                }

                $schemaProviders = [];

                if (
                    ($collection = $this->listableRelationshipCollection($field, $context)) &&
                    ($pagination = $field->pagination ?? $collection->pagination())
                ) {
                    $schemaProviders[] = $pagination;
                }

                $relatedCollections = array_map(
                    $context->api->getCollection(...),
                    $field->collections,
                );
                $relatedSchemas = [];

                foreach ($relatedCollections as $collection) {
                    foreach ($collection->resources() as $relatedResource) {
                        $relatedSchemas[] = ['$ref' => "#/components/schemas/$relatedResource"];
                    }
                }

                if ($field->nullable) {
                    $relatedSchemas[] = ['type' => 'null'];
                }

                $paths["/$type/{id}/$field->name"]['get'] = [
                    'tags' => [$type],
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ],
                        ...array_map(
                            fn(Parameter $parameter) => $parameter->getSchema($context),
                            $this->getParameters($field, $context),
                        ),
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful show related response.',
                            ...$this->responseSchema(
                                $this->resourceDocumentSchema(
                                    $context,
                                    $relatedSchemas,
                                    multiple: $field instanceof ToMany,
                                    schemaProviders: $schemaProviders,
                                ),
                                $context,
                            ),
                        ],
                    ],
                ];
            }
        }

        return ['paths' => $paths];
    }

    protected function getParameters(Relationship $field, SchemaContext $context): array
    {
        $parameters = $this->resourceDocumentParameters();

        if ($collection = $this->listableRelationshipCollection($field, $context)) {
            array_push(
                $parameters,
                ...$this->listParameters($collection, $field->defaultSort, $field->pagination),
            );
        }

        return [...$parameters, ...$this->parameters];
    }

    public function relationshipLinks(Relationship $field, SchemaContext $context): array
    {
        $links = [];

        if ($this->hasRelatedLink($field, $context)) {
            $links[] = Link::make('related')->get(
                fn($model, Context $context) => $this->relatedLink($model, $field, $context),
            );
        }

        return $links;
    }

    private function hasRelatedLink(Relationship $field, SchemaContext $context): bool
    {
        if ($field->includable) {
            return true;
        }

        $collection =
            count($field->collections) === 1
                ? $context->api->getCollection($field->collections[0])
                : null;

        return $field instanceof ToMany
            ? $collection instanceof Listable
            : $collection instanceof Findable;
    }

    private function relatedLink($model, Relationship $field, Context $context): string
    {
        return $this->resourceSelfLink($model, $context) . '/' . $field->name;
    }
}
