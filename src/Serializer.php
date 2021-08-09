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

    private function resolveDeferred(): void
    {
        $i = 0;
        while (count($this->deferred)) {
            foreach ($this->deferred as $k => $resolve) {
                $resolve();
                unset($this->deferred[$k]);
            }

            if ($i++ > 10) {
                throw new RuntimeException('Too many levels of deferred values.');
            }
        }
    }

    private function addToMap(ResourceType $resourceType, $model, array $include): array
    {
        $adapter = $resourceType->getAdapter();
        $schema = $resourceType->getSchema();

        $key = $this->key(
            $type = $resourceType->getType(),
            $id = $adapter->getId($model)
        );

        $this->map[$key] = $this->map[$key] ?? [
                'type' => $type,
                'id' => $id,
                'fields' => [],
                'links' => [],
                'meta' => []
            ];

        $url = $this->context->getApi()->getBasePath()."/$type/$id";
        $fields = $this->sparseFields($type, $schema->getFields());

        foreach ($fields as $field) {
            $name = $field->getName();

            if (isset($this->map[$key]['fields'][$name])) {
                continue;
            }

            if (! evaluate($field->getVisible(), [$model, $this->context])) {
                continue;
            }

            if ($field instanceof Schema\Attribute) {
                $this->setAttribute($key, $field, $resourceType, $model);
            } elseif ($field instanceof Schema\Relationship) {
                $this->setRelationship($key, $field, $resourceType, $model, $include, $url);
            }
        }

        $this->map[$key]['links']['self'] = new Structure\Link\SelfLink($url);
        $this->map[$key]['meta'] = $this->meta($schema->getMeta(), $model);

        return $this->map[$key];
    }

    private function setAttribute(string $key, Attribute $field, ResourceType $resourceType, $model): void
    {
        $this->defer($this->getAttributeValue($field, $resourceType, $model), function ($value) use ($key, $field) {
            if ($value instanceof DateTimeInterface) {
                $value = $value->format(DateTime::RFC3339);
            }

            $this->map[$key]['fields'][$name = $field->getName()] = new Structure\Attribute($name, $value);
        });
    }

    private function getAttributeValue(Attribute $field, ResourceType $resourceType, $model)
    {
        return ($getCallback = $field->getGetCallback())
            ? $getCallback($model, $this->context)
            : $resourceType->getAdapter()->getAttribute($model, $field);
    }

    private function setRelationship(string $key, Relationship $field, ResourceType $resourceType, $model, array $include, string $url): void
    {
        $name = $field->getName();
        $isIncluded = isset($include[$name]);
        $nestedInclude = $include[$name] ?? [];

        $members = array_merge(
            $this->relationshipLinks($field, $url),
            $this->meta($field->getMeta(), $model)
        );

        if (! $isIncluded && ! $field->hasLinkage()) {
            if ($relationship = $this->emptyRelationship($field, $members)) {
                $this->map[$key]['fields'][$name] = $relationship;
            }
            return;
        }

        $value = $this->getRelationshipValue($field, $resourceType, $model, ! $isIncluded);

        if ($field instanceof Schema\HasOne) {
            $this->defer($value, function ($value) use ($key, $field, $name, $isIncluded, $nestedInclude, $members) {
                if (! $value) {
                    $relationship = new Structure\ToNull($name, ...$members);
                } else {
                    $identifier = $isIncluded
                        ? $this->addRelated($field, $value, $nestedInclude)
                        : $this->relatedResourceIdentifier($field, $value);

                    $relationship = new Structure\ToOne($name, $identifier, ...$members);
                }

                $this->map[$key]['fields'][$name] = $relationship;
            });
        } elseif ($field instanceof Schema\HasMany) {
            $this->defer($value, function ($value) use ($key, $field, $name, $isIncluded, $nestedInclude, $members) {
                $identifiers = array_map(function ($relatedModel) use ($field, $isIncluded, $nestedInclude) {
                    return $isIncluded
                        ? $this->addRelated($field, $relatedModel, $nestedInclude)
                        : $this->relatedResourceIdentifier($field, $relatedModel);
                }, $value);

                $this->map[$key]['fields'][$name] = new Structure\ToMany(
                    $name,
                    new Structure\ResourceIdentifierCollection(...$identifiers),
                    ...$members
                );
            });
        }
    }

    private function getRelationshipValue(Relationship $field, ResourceType $resourceType, $model, bool $linkage)
    {
        if ($getCallback = $field->getGetCallback()) {
            return $getCallback($model, $this->context);
        }

        if ($field instanceof HasOne) {
            return $resourceType->getAdapter()->getHasOne($model, $field, $linkage, $this->context);
        }

        if ($field instanceof HasMany) {
            return $resourceType->getAdapter()->getHasMany($model, $field, $linkage, $this->context);
        }

        return null;
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

    private function defer($value, $callback): void
    {
        if ($value instanceof Deferred) {
            $this->deferred[] = function () use (&$data, $value, $callback) {
                $this->defer($value->resolve(), $callback);
            };
            return;
        }

        $callback($value);
    }

    private function emptyRelationship(Schema\Relationship $field, array $members): ?Structure\EmptyRelationship
    {
        if (! $members) {
            return null;
        }

        return new Structure\EmptyRelationship($field->getName(), ...$members);
    }

    /**
     * @return Structure\Internal\RelationshipMember[]
     */
    private function relationshipLinks(Schema\Relationship $field, string $url): array
    {
        // if (! $field->hasUrls()) {
            return [];
        // }

        // return [
        //     new Structure\Link\SelfLink($url.'/relationships/'.$field->getName()),
        //     new Structure\Link\RelatedLink($url.'/'.$field->getName())
        // ];
    }

    private function addRelated(Schema\Relationship $field, $model, array $include): Structure\ResourceIdentifier
    {
        $relatedResource = is_string($field->getType())
            ? $this->context->getApi()->getResourceType($field->getType())
            : $this->resourceForModel($model);

        return $this->resourceIdentifier(
            $this->addToMap($relatedResource, $model, $include)
        );
    }

    private function resourceForModel($model): ResourceType
    {
        foreach ($this->context->getApi()->getResourceTypes() as $resource) {
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

    private function relatedResourceIdentifier(Schema\Relationship $field, $model): Structure\ResourceIdentifier
    {
        $type = $field->getType();
        $relatedResource = is_string($type)
            ? $this->context->getApi()->getResourceType($type)
            : $this->resourceForModel($model);

        return $this->resourceIdentifier([
            'type' => $relatedResource->getType(),
            'id' => $relatedResource->getAdapter()->getId($model)
        ]);
    }

    /**
     * @return Structure\Internal\RelationshipMember[]
     */
    private function meta(array $items, $model): array
    {
        ksort($items);

        return array_map(function (Schema\Meta $meta) use ($model) {
            return new Structure\Meta($meta->getName(), ($meta->getValue())($model, $this->context));
        }, $items);
    }

    private function key(string $type, string $id): string
    {
        return $type.':'.$id;
    }
}
