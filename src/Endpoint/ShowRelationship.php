<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesList;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesRelationship;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesRelationshipDocument;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Parameter;
use Tobyz\JsonApiServer\SchemaContext;

class ShowRelationship implements Endpoint, ProvidesRootSchema, ProvidesRelationshipLinks
{
    use HasParameters;
    use HasResponse;
    use ResolvesModel;
    use ResolvesRelationship;
    use ResolvesList;
    use SerializesRelationshipDocument;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = $context->pathSegments();

        if (count($segments) !== 3 || $segments[1] !== 'relationships') {
            return null;
        }

        if ($context->method() !== 'GET') {
            throw new MethodNotAllowedException();
        }

        [$id, , $relationshipName] = $segments;

        $context = $this->resolveModel($context, $id);

        $field = $this->resolveRelationshipField($context, $relationshipName);

        if (!$this->hasRelationshipLink($field, $context)) {
            return null;
        }

        $context = $context->withParameters($this->getParameters($field, $context));

        $relatedData = $this->resolveRelationshipData($context, $field);

        $document = $this->serializeRelationshipDocument($field, $relatedData, $context);

        return $this->createResponse($document, $context);
    }

    public function rootSchema(SchemaContext $context): array
    {
        $type = $context->collection->name();
        $paths = [];

        foreach ($context->collection->resources() as $resourceName) {
            $resource = $context->resource($resourceName);
            $context = $context->withResource($resource);

            foreach ($resource->fields() as $field) {
                if (
                    !$field instanceof Relationship ||
                    !$this->hasRelationshipLink($field, $context)
                ) {
                    continue;
                }

                $schemaProviders = [];

                if (
                    ($collection = $this->listableRelationshipCollection($field, $context)) &&
                    ($pagination = $field->pagination ?? $collection->pagination())
                ) {
                    $schemaProviders[] = $pagination;
                }

                $paths["/$type/{id}/relationships/$field->name"]['get'] = [
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
                            'description' => 'Successful show relationship response.',
                            ...$this->responseSchema(
                                $this->relationshipDocumentSchema($context, [
                                    '$ref' => "#/components/schemas/{$resource->type()}_relationship_{$field->name}",
                                ]),
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
        $parameters = [];

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

        if ($this->hasRelationshipLink($field, $context)) {
            $links[] = Link::make('self')->get(
                fn($model, Context $context) => $this->resourceSelfLink($model, $context) .
                    '/relationships/' .
                    $field->name,
            );
        }

        return $links;
    }

    private function hasRelationshipLink(Relationship $field, SchemaContext $context): bool
    {
        if ($field->includable || $field->linkage) {
            return true;
        }

        $collection =
            count($field->collections) === 1
                ? $context->api->getCollection($field->collections[0])
                : null;

        return $field instanceof ToMany && $collection instanceof Listable;
    }
}
