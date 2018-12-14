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
            'links' => []
        ];

        foreach ($schema->fields as $name => $field) {
            if (($field instanceof Schema\Relationship && ! isset($include[$name]))
                || ! ($field->isVisible)($model, $this->request)
            ) {
                continue;
            }

            $value = $this->getValue($field, $adapter, $model);

            if ($field instanceof Schema\Attribute) {
                $value = $this->attribute($field, $value);
            } elseif ($field instanceof Schema\HasOne) {
                $value = $this->toOne($field, $value, $include[$name] ?? []);
            } elseif ($field instanceof Schema\HasMany) {
                $value = $this->toMany($field, $value, $include[$name] ?? []);
            }

            $data['fields'][$name] = $value;
        }

        $data['links']['self'] = new JsonApi\Link\SelfLink($this->api->getBaseUrl().'/'.$data['type'].'/'.$data['id']);

        $this->merge($data);

        return $data;
    }

    private function attribute(Schema\Attribute $field, $value): JsonApi\Attribute
    {
        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTime::RFC3339);
        }

        return new JsonApi\Attribute($field->name, $value);
    }

    private function toOne(Schema\Relationship $field, $value, array $include)
    {
        if (! $value) {
            return new JsonApi\ToNull($field->name);
        }

        $identifier = $this->addRelated($field, $value, $include);

        return new JsonApi\ToOne($field->name, $identifier);
    }

    private function toMany(Schema\Relationship $field, $value, array $include): JsonApi\ToMany
    {
        $identifiers = [];

        foreach ($value as $relatedModel) {
            $identifiers[] = $this->addRelated($field, $relatedModel, $include);
        }

        return new JsonApi\ToMany(
            $field->name,
            new JsonApi\ResourceIdentifierCollection(...$identifiers)
        );
    }

    private function addRelated(Schema\Relationship $field, $model, array $include): JsonApi\ResourceIdentifier
    {
        $relatedResource = $this->api->getResource($field->resource);

        return $this->resourceIdentifier(
            $this->addToMap($relatedResource, $model, $include)
        );
    }

    private function getValue(Schema\Field $field, AdapterInterface $adapter, $model)
    {
        if ($field->getter) {
            return ($field->getter)($model, $this->request);
        } elseif ($field instanceof Schema\Attribute) {
            return $adapter->getAttribute($model, $field);
        } elseif ($field instanceof Schema\HasOne) {
            return $adapter->getHasOne($model, $field);
        } elseif ($field instanceof Schema\HasMany) {
            return $adapter->getHasMany($model, $field);
        }
    }

    private function merge($data): void
    {
        $key = $data['type'].':'.$data['id'];

        if (isset($this->map[$key])) {
            $this->map[$key]['fields'] = array_merge($this->map[$key]['fields'], $data['fields']);
            $this->map[$key]['links'] = array_merge($this->map[$key]['links'], $data['links']);
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
            ...array_values($data['links'])
        );
    }

    private function resourceIdentifier(array $data): JsonApi\ResourceIdentifier
    {
        return new JsonApi\ResourceIdentifier(
            $data['type'],
            $data['id']
        );
    }
}
