<?php

namespace Tobyz\JsonApiServer;

use DateTime;
use DateTimeInterface;
use JsonApiPhp\JsonApi as Structure;
use JsonApiPhp\JsonApi\EmptyRelationship;
use JsonApiPhp\JsonApi\Link\RelatedLink;
use JsonApiPhp\JsonApi\Link\SelfLink;
use JsonApiPhp\JsonApi\ResourceIdentifier;
use JsonApiPhp\JsonApi\ResourceIdentifierCollection;
use JsonApiPhp\JsonApi\ToMany;
use JsonApiPhp\JsonApi\ToOne;
use Psr\Http\Message\ServerRequestInterface as Request;
use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\Relationship;

final class Serializer
{
    protected $api;
    protected $request;
    protected $map = [];
    protected $primary = [];

    public function __construct(JsonApi $api, Request $request)
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
            'type' => $type = $resource->getType(),
            'id' => $adapter->getId($model),
            'fields' => [],
            'links' => [],
            'meta' => []
        ];

        $resourceUrl = $this->api->getBaseUrl().'/'.$data['type'].'/'.$data['id'];

        $fields = $schema->getFields();

        $queryParams = $this->request->getQueryParams();

        if (isset($queryParams['fields'][$type])) {
            $fields = array_intersect_key($fields, array_flip(explode(',', $queryParams['fields'][$type])));
        }

        ksort($fields);

        $key = $data['type'].':'.$data['id'];

        foreach ($fields as $name => $field) {
            if (isset($this->map[$key]['fields'][$name])) {
                continue;
            }

            if (! evaluate($field->getVisible(), [$model, $this->request])) {
                continue;
            }

            if ($field instanceof Schema\Attribute) {
                $value = $this->attribute($field, $model, $adapter);
            } elseif ($field instanceof Schema\Relationship) {
                $isIncluded = isset($include[$name]);
                $isLinkage = evaluate($field->getLinkage(), [$this->request]);

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

        $data['links']['self'] = new SelfLink($resourceUrl);

        $metas = $schema->getMeta();

        ksort($metas);

        foreach ($metas as $name => $meta) {
            $data['meta'][$name] = new Structure\Meta($meta->name, ($meta->value)($this->request, $model));
        }

        $this->merge($data);

        return $data;
    }

    private function attribute(Attribute $field, $model, AdapterInterface $adapter): Structure\Attribute
    {
        if ($getter = $field->getGetter()) {
            $value = $getter($model, $this->request);
        } else {
            $value = $adapter->getAttribute($model, $field);
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTime::RFC3339);
        }

        return new Structure\Attribute($field->getName(), $value);
    }

    private function toOne(Schema\HasOne $field, $model, AdapterInterface $adapter, bool $isIncluded, bool $isLinkage, array $include, string $resourceUrl)
    {
        $links = $this->getRelationshipLinks($field, $resourceUrl);

        $value = $isIncluded ? (($getter = $field->getGetter()) ? $getter($model, $this->request) : $adapter->getHasOne($model, $field)) : ($isLinkage && $field->getLoadable() ? $adapter->getHasOneId($model, $field) : null);

        if (! $value) {
            return new Structure\ToNull(
                $field->getName(),
                ...$links
            );
        }

        if ($isIncluded) {
            $identifier = $this->addRelated($field, $value, $include);
        } else {
            $identifier = $this->relatedResourceIdentifier($field, $value);
        }


        return new ToOne(
            $field->getName(),
            $identifier,
            ...$links
        );
    }

    private function toMany(Schema\HasMany $field, $model, AdapterInterface $adapter, bool $isIncluded, bool $isLinkage, array $include, string $resourceUrl)
    {
        if ($getter = $field->getGetter()) {
            $value = $getter($model, $this->request);
        } else {
            $value = ($isLinkage || $isIncluded) ? $adapter->getHasMany($model, $field) : null;
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

        return new ToMany(
            $field->getName(),
            new ResourceIdentifierCollection(...$identifiers),
            ...$this->getRelationshipLinks($field, $resourceUrl)
        );
    }

    private function emptyRelationship(Relationship $field, string $resourceUrl): EmptyRelationship
    {
        return new EmptyRelationship(
            $field->getName(),
            ...$this->getRelationshipLinks($field, $resourceUrl)
        );
    }

    private function getRelationshipLinks(Relationship $field, string $resourceUrl): array
    {
        if (! $field->hasLinks()) {
            return [];
        }

        return [
            new SelfLink($resourceUrl.'/relationships/'.$field->getName()),
            new RelatedLink($resourceUrl.'/'.$field->getName())
        ];
    }

    private function addRelated(Relationship $field, $model, array $include): ResourceIdentifier
    {
        $relatedResource = $field->getType() ? $this->api->getResource($field->getType()) : $this->resourceForModel($model);

        return $this->resourceIdentifier(
            $this->addToMap($relatedResource, $model, $include)
        );
    }

    private function resourceForModel($model)
    {
        foreach ($this->api->getResources() as $resource) {
            if ($resource->getAdapter()->handles($model)) {
                return $resource;
            }
        }

        throw new \RuntimeException('No resource defined to handle model of type '.get_class($model));
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

    private function resourceObject(array $data): Structure\ResourceObject
    {
        return new Structure\ResourceObject(
            $data['type'],
            $data['id'],
            ...array_values($data['fields']),
            ...array_values($data['links']),
            ...array_values($data['meta'])
        );
    }

    private function resourceIdentifier(array $data): Structure\ResourceIdentifier
    {
        return new Structure\ResourceIdentifier(
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
