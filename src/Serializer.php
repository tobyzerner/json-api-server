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
use RuntimeException;
use Tobyz\JsonApiServer\Schema\Attribute;
use Tobyz\JsonApiServer\Schema\Field;
use Tobyz\JsonApiServer\Schema\HasMany;
use Tobyz\JsonApiServer\Schema\HasOne;
use Tobyz\JsonApiServer\Schema\Meta;
use Tobyz\JsonApiServer\Schema\Relationship;

final class Serializer
{
    private $context;
    private $map = [];
    private $primary = [];
    private $deferred = [];

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Add a primary resource to the document.
     */
    public function add(ResourceType $resourceType, $model, array $include): void
    {
        $data = $this->addToMap($resourceType, $model, $include);

        $this->primary[] = $this->key($data['type'], $data['id']);
    }

    /**
     * Serialize the primary and included resources into a JSON:API resource objects.
     */
    public function serialize(): array
    {
        $this->resolveDeferred();

        $keys = array_flip($this->primary);
        $primary = array_values(array_intersect_key($this->map, $keys));
        $included = array_values(array_diff_key($this->map, $keys));

        return [
            $this->resourceObjects($primary),
            $this->resourceObjects($included),
        ];
    }

    private function addToMap(ResourceType $resourceType, $model, array $include): array
    {
        $adapter = $resourceType->getAdapter();
        $schema = $resourceType->getSchema();

        $key = $this->key(
            $type = $resourceType->getType(),
            $id = $adapter->getId($model)
        );

        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        $this->map[$key] = [
            'type' => $type,
            'id' => $id,
            'fields' => [],
            'links' => [
                'self' => new Structure\Link\SelfLink($url = $resourceType->url($model, $this->context)),
            ],
            'meta' => $this->meta($schema->getMeta(), $model)
        ];

        $fields = $this->sparseFields($type, $schema->getFields());

        foreach ($fields as $field) {
            if (! evaluate($field->getVisible(), [$model, $this->context])) {
                continue;
            }

            if ($field instanceof Attribute) {
                $this->resolveAttribute($key, $field, $resourceType, $model);
            } elseif ($field instanceof Relationship) {
                $this->resolveRelationship($key, $field, $resourceType, $model, $include, $url);
            }
        }

        return $this->map[$key];
    }

    private function key(string $type, string $id): string
    {
        return $type.':'.$id;
    }

    /**
     * @return Structure\Internal\RelationshipMember[]
     */
    private function meta(array $items, $model): array
    {
        ksort($items);

        return array_map(function (Meta $meta) use ($model) {
            return new Structure\Meta($meta->getName(), ($meta->getValue())($model, $this->context));
        }, $items);
    }

    private function sparseFields(string $type, array $fields): array
    {
        $queryParams = $this->context->getRequest()->getQueryParams();

        if (isset($queryParams['fields'][$type])) {
            $requested = $queryParams['fields'][$type];
            $requested = is_array($requested) ? $requested : explode(',', $requested);
            $fields = array_intersect_key($fields, array_flip($requested));
        }

        return $fields;
    }

    private function resolveAttribute(string $key, Attribute $field, ResourceType $resourceType, $model): void
    {
        $value = $this->getAttributeValue($field, $resourceType, $model);

        $this->whenResolved($value, function ($value) use ($key, $field) {
            if ($value instanceof DateTimeInterface) {
                $value = $value->format(DateTime::RFC3339);
            }

            $this->setField($key, $field, new Structure\Attribute($field->getName(), $value));
        });
    }

    private function resolveRelationship(string $key, Relationship $field, ResourceType $resourceType, $model, array $include, string $url): void
    {
        $name = $field->getName();
        $linkageOnly = ! isset($include[$name]);
        $nestedInclude = $include[$name] ?? null;

        $members = array_merge(
            $this->relationshipLinks($url, $field),
            $this->meta($field->getMeta(), $model)
        );

        if ($linkageOnly && ! $field->hasLinkage()) {
            if ($relationship = $this->emptyRelationship($field, $members)) {
                $this->setField($key, $field, $relationship);
            }
            return;
        }

        $value = $this->getRelationshipValue($field, $resourceType, $model, $linkageOnly);

        $this->whenResolved($value, function ($value) use ($key, $field, $nestedInclude, $members) {
            if ($structure = $this->buildRelationship($field, $value, $nestedInclude, $members)) {
                $this->setField($key, $field, $structure);
            }
        });
    }

    private function getAttributeValue(Attribute $field, ResourceType $resourceType, $model)
    {
        if ($getCallback = $field->getGetCallback()) {
            return $getCallback($model, $this->context);
        }

        return $resourceType->getAdapter()->getAttribute($model, $field);
    }

    private function whenResolved($value, $callback): void
    {
        if ($value instanceof Deferred) {
            $this->deferred[] = function () use (&$data, $value, $callback) {
                $this->whenResolved($value->resolve(), $callback);
            };
            return;
        }

        $callback($value);
    }

    private function setField(string $key, Field $field, $value): void
    {
        $this->map[$key]['fields'][$field->getName()] = $value;
    }

    /**
     * @return Structure\Internal\RelationshipMember[]
     */
    private function relationshipLinks(string $url, Relationship $field): array
    {
        return [];

        // if (! $field->hasUrls()) {
        //     return [];
        // }

        // return [
        //     new Structure\Link\SelfLink($url.'/relationships/'.$field->getName()),
        //     new Structure\Link\RelatedLink($url.'/'.$field->getName())
        // ];
    }

    private function emptyRelationship(Relationship $field, array $members): ?Structure\EmptyRelationship
    {
        if (! $members) {
            return null;
        }

        return new Structure\EmptyRelationship($field->getName(), ...$members);
    }

    private function getRelationshipValue(Relationship $field, ResourceType $resourceType, $model, bool $linkageOnly)
    {
        if ($getCallback = $field->getGetCallback()) {
            return $getCallback($model, $linkageOnly, $this->context);
        }

        if ($field instanceof HasOne) {
            return $resourceType->getAdapter()->getHasOne($model, $field, $linkageOnly, $this->context);
        }

        if ($field instanceof HasMany) {
            return $resourceType->getAdapter()->getHasMany($model, $field, $linkageOnly, $this->context);
        }

        return null;
    }

    private function buildRelationship(Relationship $field, $value, ?array $nestedInclude, array $members): ?Structure\Internal\ResourceField
    {
        $name = $field->getName();

        if ($field instanceof HasOne) {
            if (! $value) {
                return new Structure\ToNull($name, ...$members);
            }

            return new Structure\ToOne(
                $name,
                $this->addRelatedResource($field, $value, $nestedInclude),
                ...$members
            );
        }

        if ($field instanceof HasMany) {
            $identifiers = array_map(function ($relatedModel) use ($field, $nestedInclude) {
                return $this->addRelatedResource($field, $relatedModel, $nestedInclude);
            }, $value);

            return new Structure\ToMany(
                $name,
                new Structure\ResourceIdentifierCollection(...$identifiers),
                ...$members
            );
        }

        return null;
    }

    private function addRelatedResource(Relationship $field, $model, ?array $include): Structure\ResourceIdentifier
    {
        $relatedResourceType = $this->resourceTypeForModel($field, $model);

        if ($include === null) {
            return $this->resourceIdentifier([
                'type' => $relatedResourceType->getType(),
                'id' => $relatedResourceType->getAdapter()->getId($model)
            ]);
        }

        return $this->resourceIdentifier(
            $this->addToMap($relatedResourceType, $model, $include)
        );
    }

    private function resourceTypeForModel(Relationship $field, $model): ResourceType
    {
        if (is_string($type = $field->getType())) {
            return $this->context->getApi()->getResourceType($type);
        }

        foreach ($this->context->getApi()->getResourceTypes() as $resourceType) {
            if ($resourceType->getAdapter()->represents($model)) {
                return $resourceType;
            }
        }

        throw new RuntimeException('No resource type defined to represent model '.get_class($model));
    }

    private function resourceIdentifier(array $data): Structure\ResourceIdentifier
    {
        return new Structure\ResourceIdentifier($data['type'], $data['id']);
    }

    private function resolveDeferred(): void
    {
        $i = 0;
        while (count($this->deferred)) {
            foreach ($this->deferred as $k => $resolve) {
                $resolve();
                unset($this->deferred[$k]);
            }

            if ($i++ > 10) {
                throw new RuntimeException('Too many levels of deferred values');
            }
        }
    }

    private function resourceObjects(array $items): array
    {
        return array_map([$this, 'resourceObject'], $items);
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
}
