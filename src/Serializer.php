<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer;

use DateTime;
use DateTimeInterface;
use JsonApiPhp\JsonApi as Structure;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

final class Serializer
{
    private $api;
    private $request;
    private $map = [];
    private $primary = [];

    public function __construct(JsonApi $api, Request $request)
    {
        $this->api = $api;
        $this->request = $request;
    }

    /**
     * Add a primary resource to the document.
     */
    public function add(ResourceType $resource, $model, array $include, bool $single = false): void
    {
        $data = $this->addToMap($resource, $model, $include, $single);

        $this->primary[] = $this->key($data);
    }

    /**
     * Get the serialized primary resources.
     */
    public function primary(): array
    {
        $primary = array_map(function ($key) {
            return $this->map[$key];
        }, $this->primary);

        return $this->resourceObjects($primary);
    }

    /**
     * Get the serialized included resources.
     */
    public function included(): array
    {
        $included = array_values(array_diff_key($this->map, array_flip($this->primary)));

        return $this->resourceObjects($included);
    }

    private function addToMap(ResourceType $resource, $model, array $include, bool $single = false): array
    {
        $adapter = $resource->getAdapter();
        $schema = $resource->getSchema();

        $data = [
            'type' => $type = $resource->getType(),
            'id' => $id = $adapter->getId($model),
            'fields' => [],
            'links' => [],
            'meta' => []
        ];

        $key = $this->key($data);
        $url = $this->api->getBaseUrl()."/$type/$id";
        $fields = $schema->getFields();
        $queryParams = $this->request->getQueryParams();

        if (isset($queryParams['fields'][$type])) {
            $fields = array_intersect_key($fields, array_flip(explode(',', $queryParams['fields'][$type])));
        }

        foreach ($fields as $name => $field) {
            if (isset($this->map[$key]['fields'][$name])) {
                continue;
            }

            if ($field->isSingle() && ! $single) {
                continue;
            }

            if (! evaluate($field->isVisible(), [$model, $this->request])) {
                continue;
            }

            if ($field instanceof Schema\Attribute) {
                $value = $this->attribute($field, $resource, $model);
            } elseif ($field instanceof Schema\Relationship) {
                $isIncluded = isset($include[$name]);
                $relationshipInclude = $isIncluded ? ($include[$name] ?? []) : null;
                $links = $this->relationshipLinks($field, $url);
                $meta = $this->meta($field->getMeta(), $model);
                $members = array_merge($links, $meta);

                if (! $isIncluded && ! $field->isLinkage()) {
                    $value = $this->emptyRelationship($field, $members);
                } elseif ($field instanceof Schema\HasOne) {
                    $value = $this->toOne($field, $members, $resource, $model, $relationshipInclude, $single);
                } elseif ($field instanceof Schema\HasMany) {
                    $value = $this->toMany($field, $members, $resource, $model, $relationshipInclude);
                }
            }

            if (! empty($value)) {
                $data['fields'][$name] = $value;
            }
        }

        $data['links']['self'] = new Structure\Link\SelfLink($url);
        $data['meta'] = $this->meta($schema->getMeta(), $model);

        $this->merge($data);

        return $data;
    }

    private function merge($data): void
    {
        $key = $this->key($data);

        if (isset($this->map[$key])) {
            $this->map[$key]['fields'] = array_merge($this->map[$key]['fields'], $data['fields']);
            $this->map[$key]['links'] = array_merge($this->map[$key]['links'], $data['links']);
            $this->map[$key]['meta'] = array_merge($this->map[$key]['meta'], $data['meta']);
        } else {
            $this->map[$key] = $data;
        }
    }

    private function attribute(Schema\Attribute $field, ResourceType $resource, $model): Structure\Attribute
    {
        if ($getCallback = $field->getGetCallback()) {
            $value = $getCallback($model, $this->request);
        } else {
            $value = $resource->getAdapter()->getAttribute($model, $field);
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format(DateTime::RFC3339);
        }

        return new Structure\Attribute($field->getName(), $value);
    }

    private function toOne(Schema\HasOne $field, array $members, ResourceType $resource, $model, ?array $include, bool $single)
    {
        $included = $include !== null;

        $model = ($getCallback = $field->getGetCallback())
            ? $getCallback($model, $this->request)
            : $resource->getAdapter()->getHasOne($model, $field, ! $included);

        if (! $model) {
            return new Structure\ToNull($field->getName(), ...$members);
        }

        $identifier = $include !== null
            ? $this->addRelated($field, $model, $include, $single)
            : $this->relatedResourceIdentifier($field, $model);

        return new Structure\ToOne($field->getName(), $identifier, ...$members);
    }

    private function toMany(Schema\HasMany $field, array $members, ResourceType $resource, $model, ?array $include)
    {
        $included = $include !== null;

        $models = ($getCallback = $field->getGetCallback())
            ? $getCallback($model, $this->request)
            : $resource->getAdapter()->getHasMany($model, $field, ! $included);

        $identifiers = [];

        foreach ($models as $relatedModel) {
            $identifiers[] = $included
                ? $this->addRelated($field, $relatedModel, $include)
                : $this->relatedResourceIdentifier($field, $relatedModel);
        }

        return new Structure\ToMany(
            $field->getName(),
            new Structure\ResourceIdentifierCollection(...$identifiers),
            ...$members
        );
    }

    private function emptyRelationship(Schema\Relationship $field, array $members): ?Structure\EmptyRelationship
    {
        if (! $members) {
            return null;
        }

        return new Structure\EmptyRelationship($field->getName(), ...$members);
    }

    /**
     * @return Structure\Internal\RelationshipMember
     */
    private function relationshipLinks(Schema\Relationship $field, string $url): array
    {
        if (! $field->isLinks()) {
            return [];
        }

        return [
            new Structure\Link\SelfLink($url.'/relationships/'.$field->getName()),
            new Structure\Link\RelatedLink($url.'/'.$field->getName())
        ];
    }

    private function addRelated(Schema\Relationship $field, $model, array $include, bool $single = false): Structure\ResourceIdentifier
    {
        $relatedResource = is_string($field->getType())
            ? $this->api->getResource($field->getType())
            : $this->resourceForModel($model);

        return $this->resourceIdentifier(
            $this->addToMap($relatedResource, $model, $include, $single)
        );
    }

    private function resourceForModel($model): ResourceType
    {
        foreach ($this->api->getResources() as $resource) {
            if ($resource->getAdapter()->represents($model)) {
                return $resource;
            }
        }

        throw new RuntimeException('No resource defined to represent model of type '.get_class($model));
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
        return new Structure\ResourceIdentifier($data['type'], $data['id']);
    }

    private function relatedResourceIdentifier(Schema\Relationship $field, $model)
    {
        $type = $field->getType();
        $relatedResource = is_string($type)
            ? $this->api->getResource($type)
            : $this->resourceForModel($model);

        return $this->resourceIdentifier([
            'type' => $relatedResource->getType(),
            'id' => $relatedResource->getAdapter()->getId($model)
        ]);
    }

    /**
     * @return Structure\Internal\RelationshipMember
     */
    private function meta(array $items, $model): array
    {
        ksort($items);

        return array_map(function (Schema\Meta $meta) use ($model) {
            return new Structure\Meta($meta->getName(), ($meta->getValue())($model, $this->request));
        }, $items);
    }

    private function key(array $data)
    {
        return $data['type'].':'.$data['id'];
    }
}
