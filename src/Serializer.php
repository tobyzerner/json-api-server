<?php

namespace Tobscure\JsonApiServer;

use DateTime;
use DateTimeInterface;
use JsonApiPhp\JsonApi;
use Psr\Http\Message\ServerRequestInterface as Request;
use Tobscure\JsonApiServer\Adapter\AdapterInterface;

class Serializer
{
    protected $api;
    protected $request;
    protected $map = [];
    protected $primary = [];

    public function __construct(Api $api, Request $request)
    {
        $this->api = $api;
        $this->request = $request;
    }

    public function add(ResourceType $resource, $model, array $include)
    {
        $data = $this->addToMap($resource, $model, $include);

        $this->primary[] = $data['type'].':'.$data['id'];
    }

    private function addToMap(ResourceType $resource, $model, array $include)
    {
        $adapter = $resource->getAdapter();
        $schema = $resource->getSchema();

        $data = [
            'type' => $resource->getType(),
            'id' => $adapter->getId($model),
            'fields' => [],
            'links' => [],
            'meta' => []
        ];

        $resourceUrl = $this->api->getBaseUrl().'/'.$data['type'].'/'.$data['id'];

        ksort($schema->fields);

        foreach ($schema->fields as $name => $field) {
            if (! ($field->isVisible)($this->request, $model)) {
                continue;
            }

            if ($field instanceof Schema\Attribute) {
                $value = $this->attribute($field, $model, $adapter);
            } elseif ($field instanceof Schema\Relationship) {
                $isIncluded = isset($include[$name]);
                $isLinkage = ($field->linkage)($this->request);

                if (! $isIncluded && ! $isLinkage) {
                    $value = $this->emptyRelationship($field, $resourceUrl);
                } elseif ($field instanceof Schema\HasOne) {
                    $value = $this->toOne($field, $model, $adapter, $isIncluded, $isLinkage, $include[$name] ?? [], $resourceUrl);
                } elseif ($field instanceof Schema\HasMany) {
                    $value = $this->toMany($field, $model, $adapter, $isIncluded, $isLinkage, $include[$name] ?? [], $resourceUrl);
                }
            }

            $data['fields'][$name] = $value;
        }

        $data['links']['self'] = new JsonApi\Link\SelfLink($resourceUrl);

        ksort($schema->meta);

        foreach ($schema->meta as $name => $meta) {
            $data['meta'][$name] = new JsonApi\Meta($meta->name, ($meta->value)($this->request, $model));
        }

        $this->merge($data);

        return $data;
    }

    private function attribute(Schema\Attribute $field, $model, AdapterInterface $adapter): JsonApi\Attribute
    {
        if ($field->getter) {
            $value = ($field->getter)($this->request, $model);
        } else {
            $value = $adapter->getAttribute($model, $field);
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTime::RFC3339);
        }

        return new JsonApi\Attribute($field->name, $value);
    }

    private function toOne(Schema\HasOne $field, $model, AdapterInterface $adapter, bool $isIncluded, bool $isLinkage, array $include, string $resourceUrl)
    {
        $links = $this->getRelationshipLinks($field, $resourceUrl);

        if ($field->getter) {
            $value = ($field->getter)($this->request, $model);
        } else {
            $value = $isIncluded ? $adapter->getHasOne($model, $field) : ($isLinkage && $field->loadable ? $adapter->getHasOneId($model, $field) : null);
        }

        if (! $value) {
            return new JsonApi\ToNull(
                $field->name,
                ...$links
            );
        }

        if ($isIncluded) {
            $identifier = $this->addRelated($field, $value, $include);
        } else {
            $identifier = $this->relatedResourceIdentifier($field, $value);
        }


        return new JsonApi\ToOne(
            $field->name,
            $identifier,
            ...$links
        );
    }

    private function toMany(Schema\HasMany $field, $model, AdapterInterface $adapter, bool $isIncluded, bool $isLinkage, array $include, string $resourceUrl)
    {
        if ($field->getter) {
            $value = ($field->getter)($this->request, $model);
        } else {
            $value = $isLinkage ? $adapter->getHasMany($model, $field) : null;
        }

        $identifiers = [];

        if ($isIncluded) {
            foreach ($value as $relatedModel) {
                $identifiers[] = $this->addRelated($field, $relatedModel, $include);
            }
        } else {
            foreach ($value as $relatedModel) {
                $identifiers[] = $this->relatedResourceIdentifier($field, $relatedModel);
            }
        }

        return new JsonApi\ToMany(
            $field->name,
            new JsonApi\ResourceIdentifierCollection(...$identifiers),
            ...$this->getRelationshipLinks($field, $resourceUrl)
        );
    }

    private function emptyRelationship(Schema\Relationship $field, string $resourceUrl): JsonApi\EmptyRelationship
    {
        return new JsonApi\EmptyRelationship(
            $field->name,
            ...$this->getRelationshipLinks($field, $resourceUrl)
        );
    }

    private function getRelationshipLinks(Schema\Relationship $field, string $resourceUrl): array
    {
        if (! $field->hasLinks) {
            return [];
        }

        return [
            new JsonApi\Link\SelfLink($resourceUrl.'/relationships/'.$field->name),
            new JsonApi\Link\RelatedLink($resourceUrl.'/'.$field->name)
        ];
    }

    private function addRelated(Schema\Relationship $field, $model, array $include): JsonApi\ResourceIdentifier
    {
        $relatedResource = $this->api->getResource($field->resource);

        return $this->resourceIdentifier(
            $this->addToMap($relatedResource, $model, $include)
        );
    }

    private function merge($data): void
    {
        $key = $data['type'].':'.$data['id'];

        if (isset($this->map[$key])) {
            $this->map[$key]['fields'] = array_merge($this->map[$key]['fields'], $data['fields']);
            $this->map[$key]['links'] = array_merge($this->map[$key]['links'], $data['links']);
            $this->map[$key]['meta'] = array_merge($this->map[$key]['meta'], $data['meta']);
        } else {
            $this->map[$key] = $data;
        }
    }

    public function primary(): array
    {
        $primary = array_values(array_intersect_key($this->map, array_flip($this->primary)));

        return $this->resourceObjects($primary);
    }

    public function included(): array
    {
        $included = array_values(array_diff_key($this->map, array_flip($this->primary)));

        return $this->resourceObjects($included);
    }

    private function resourceObjects(array $items): array
    {
        return array_map(function ($data) {
            return $this->resourceObject($data);
        }, $items);
    }

    private function resourceObject(array $data): JsonApi\ResourceObject
    {
        return new JsonApi\ResourceObject(
            $data['type'],
            $data['id'],
            ...array_values($data['fields']),
            ...array_values($data['links']),
            ...array_values($data['meta'])
        );
    }

    private function resourceIdentifier(array $data): JsonApi\ResourceIdentifier
    {
        return new JsonApi\ResourceIdentifier(
            $data['type'],
            $data['id']
        );
    }

    private function relatedResourceIdentifier(Schema\Relationship $field, $model)
    {
        $relatedResource = $this->api->getResource($field->resource);

        return $this->resourceIdentifier([
            'type' => $field->resource,
            'id' => $relatedResource->getAdapter()->getId($model)
        ]);
    }
}
