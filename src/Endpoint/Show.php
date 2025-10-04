<?php

namespace Tobyz\JsonApiServer\Endpoint;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Context;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsOpenApiPaths;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsRelationshipDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\BuildsResourceDocument;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasResponse;
use Tobyz\JsonApiServer\Endpoint\Concerns\HasSchema;
use Tobyz\JsonApiServer\Endpoint\Concerns\ListsResources;
use Tobyz\JsonApiServer\Endpoint\Concerns\ShowsResources;
use Tobyz\JsonApiServer\Exception\ForbiddenException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotFoundException;
use Tobyz\JsonApiServer\JsonApi;
use Tobyz\JsonApiServer\OpenApi\OpenApiPathsProvider;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Findable;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\RelatedListable;
use Tobyz\JsonApiServer\Schema\Concerns\HasDescription;
use Tobyz\JsonApiServer\Schema\Concerns\HasVisibility;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Field\ToMany;

use function Tobyz\JsonApiServer\json_api_response;
use function Tobyz\JsonApiServer\resolve_value;

class Show implements Endpoint, ResourceEndpoint, RelationshipEndpoint, OpenApiPathsProvider
{
    use HasVisibility;
    use HasDescription;
    use HasResponse;
    use HasSchema;
    use FindsResources;
    use ListsResources;
    use ShowsResources;
    use BuildsResourceDocument;
    use BuildsRelationshipDocument;
    use BuildsOpenApiPaths;

    public static function make(): static
    {
        return new static();
    }

    public function handle(Context $context): ?ResponseInterface
    {
        $segments = explode('/', $context->path());
        $count = count($segments);

        if ($count < 2 || $count > 4 || ($count === 4 && $segments[2] !== 'relationships')) {
            return null;
        }

        if ($context->request->getMethod() !== 'GET') {
            throw new MethodNotAllowedException();
        }

        $model = $this->findResource($context, $segments[1]);

        $context = $context
            ->withModel($model)
            ->withResource($context->resource($context->collection->resource($model, $context)));

        if (!$this->isVisible($context)) {
            throw new ForbiddenException();
        }

        if ($count === 2) {
            $response = json_api_response($this->buildResourceDocument($model, $context));

            return $this->applyResponseHooks($response, $context);
        }

        $isRelatedEndpoint = $count === 3;
        $relationshipName = $isRelatedEndpoint ? $segments[2] : $segments[3];
        $relationshipField = $context->fields($context->resource)[$relationshipName] ?? null;

        if (
            !$relationshipField instanceof Relationship ||
            !($isRelatedEndpoint
                ? $this->hasRelatedLink($relationshipField, $context)
                : $this->hasRelationshipLink($relationshipField, $context))
        ) {
            throw new NotFoundException();
        }

        if (!$relationshipField->isVisible($context->withField($relationshipField))) {
            throw new ForbiddenException();
        }

        $relatedCollections = array_map(
            fn($collection) => $context->api->getCollection($collection),
            $relationshipField->collections,
        );

        if (
            $relationshipField instanceof ToMany &&
            count($relatedCollections) === 1 &&
            $context->resource instanceof RelatedListable &&
            $relatedCollections[0] instanceof Listable &&
            ($relatedQuery = $context->resource->relatedQuery($model, $relationshipField, $context))
        ) {
            $relatedData = $this->listResources(
                $relatedQuery,
                $relatedCollections[0],
                $context,
                $relationshipField->defaultSort,
                $relationshipField->pagination,
            );
        } else {
            $relatedData = resolve_value($relationshipField->getValue($context->withInclude([])));
        }

        if ($isRelatedEndpoint) {
            $document = $this->buildResourceDocument($relatedData, $context, $relatedCollections);

            $document['links']['self'] ??= $this->relatedLink($model, $relationshipField, $context);

            return json_api_response($document);
        }

        return json_api_response(
            $this->buildRelationshipDocument($relationshipField, $relatedData, $context),
        );
    }

    public function relationshipLinks($model, Relationship $field, Context $context): array
    {
        $links = [];

        if ($this->hasRelationshipLink($field, $context)) {
            $links['self'] = $this->selfLink($model, $context) . '/relationships/' . $field->name;
        }

        if ($this->hasRelatedLink($field, $context)) {
            $links['related'] = $this->relatedLink($model, $field, $context);
        }

        return $links;
    }

    public function seeOther(callable $callback): static
    {
        $this->response(function ($response, $model, $context) use ($callback) {
            $result = $callback($model, $context);

            if ($result !== null) {
                if (!is_string($result)) {
                    throw new RuntimeException('seeOther() callback must return a string');
                }

                $location = $context->api->basePath . '/' . ltrim($result, '/');

                return json_api_response(status: 303)->withHeader('Location', $location);
            }

            return $response;
        });

        $this->schema([
            'responses' => [
                '303' => [
                    'headers' => [
                        'Location' => ['schema' => ['type' => 'string']],
                    ],
                ],
            ],
        ]);

        return $this;
    }

    public function getOpenApiPaths(Collection $collection, JsonApi $api): array
    {
        $type = $collection->name();

        $idParameter = [
            [
                'name' => 'id',
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ],
        ];

        $response = [
            'content' => $this->buildOpenApiContent(
                array_map(
                    fn($resource) => ['$ref' => "#/components/schemas/$resource"],
                    $collection->resources(),
                ),
            ),
        ];

        if ($headers = $this->getHeadersSchema($api)) {
            $response['headers'] = $headers;
        }

        $paths = [
            "/$type/{id}" => [
                'get' => [
                    'description' => $this->getDescription() ?: "Retrieve $type resource",
                    'tags' => [$type],
                    'parameters' => $idParameter,
                    'responses' => [
                        '200' => $response,
                    ],
                ],
            ],
        ];

        $context = new Context($api, new ServerRequest('GET', '/'));

        foreach ($collection->resources() as $resource) {
            $resource = $api->getResource($resource);

            foreach ($resource->fields() as $field) {
                if (!$field instanceof Relationship) {
                    continue;
                }

                if ($this->hasRelatedLink($field, $context)) {
                    $relatedResources = [];

                    foreach ($field->collections as $relatedCollection) {
                        $relatedCollection = $api->getCollection($relatedCollection);

                        foreach ($relatedCollection->resources() as $relatedResource) {
                            $relatedResources[] = [
                                '$ref' => "#/components/schemas/$relatedResource",
                            ];
                        }
                    }

                    if ($field->nullable) {
                        $relatedResources[] = ['type' => 'null'];
                    }

                    $paths["/$type/{id}/$field->name"]['get'] = [
                        'description' => "Retrieve related $field->name",
                        'tags' => [$type],
                        'parameters' => $idParameter,
                        'responses' => [
                            '200' => [
                                'content' => $this->buildOpenApiContent(
                                    $relatedResources,
                                    $field instanceof ToMany,
                                ),
                            ],
                        ],
                    ];
                }

                if ($this->hasRelationshipLink($field, $context)) {
                    $paths["/$type/{id}/relationships/$field->name"]['get'] = [
                        'description' => "Retrieve $field->name relationship",
                        'tags' => [$type],
                        'parameters' => $idParameter,
                        'responses' => [
                            '200' => [
                                'content' => [
                                    JsonApi::MEDIA_TYPE => [
                                        'schema' => [
                                            '$ref' => "#/components/schemas/{$type}_{$field->name}",
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            }
        }

        return $this->mergeSchema($paths);
    }

    private function hasRelatedLink(Relationship $field, Context $context): bool
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

    private function hasRelationshipLink(Relationship $field, Context $context): bool
    {
        if ($field->includable || $field->hasLinkage($context)) {
            return true;
        }

        $collection =
            count($field->collections) === 1
                ? $context->api->getCollection($field->collections[0])
                : null;

        return $field instanceof ToMany && $collection instanceof Listable;
    }

    private function relatedLink($model, Relationship $field, Context $context): string
    {
        return $this->selfLink($model, $context) . '/' . $field->name;
    }
}
