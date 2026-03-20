<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Psr\Http\Message\ResponseInterface;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsRelationshipPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasParameters;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesList;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesModel;
use Tobyz\JsonApiServer\Endpoint\Concerns\ResolvesRelationship;
use Tobyz\JsonApiServer\Endpoint\Concerns\SerializesRelationshipDocument;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\OpenApi\ProvidesRootSchema;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Schema\Concerns\HasSchema;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;
use Tobyz\JsonApiServer\SchemaContext;

class ShowRelationship implements Endpoint, ProvidesRootSchema, ProvidesRelationshipLinks
{
    use BuildsOpenApiPaths;
    use BuildsRelationshipPaths;
    use HasParameters;
    use HasResponse;
    use HasSchema;
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
        return $this->relationshipPaths(
            $context,
            function (string $type, $resource, Relationship $field, SchemaContext $resourceContext): ?array {
                if (!$this->hasRelationshipLink($field, $resourceContext)) {
                    return null;
                }

                return [
                    "/$type/{id}/relationships/$field->name",
                    [
                        'get' => $this->mergeSchema([
                            'tags' => [$type],
                            'parameters' => $this->openApiResourceParameters(
                                $resourceContext,
                                $this->getParameters($field, $resourceContext),
                            ),
                            'responses' => [
                                '200' => [
                                    'description' => 'Successful show relationship response.',
                                    ...$this->responseSchema(
                                        $this->relationshipDocumentSchema(
                                            $resourceContext,
                                            $this->openApiRelationshipSchemaRef(
                                                $resource->type(),
                                                $field->name,
                                            ),
                                        ),
                                        $resourceContext,
                                    ),
                                ],
                            ],
                        ]),
                    ],
                ];
            },
        );
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
        return $this->hasRelationshipLink($field, $context)
            ? [$this->relationshipSelfLinkDefinition($field)]
            : [];
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
