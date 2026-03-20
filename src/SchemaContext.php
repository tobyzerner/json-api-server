<?php

namespace Tobyz\JsonApiServer;

use ArrayObject;
use Tobyz\JsonApiServer\Endpoint\Endpoint;
use Tobyz\JsonApiServer\Endpoint\ProvidesRelationshipLinks;
use Tobyz\JsonApiServer\Endpoint\ProvidesResourceLinks;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Link;
use Tobyz\JsonApiServer\Schema\Meta;
use WeakMap;

class SchemaContext
{
    public ?Collection $collection = null;
    public ?Resource $resource = null;
    public ?Endpoint $endpoint = null;
    public ?Field $field = null;

    private WeakMap $endpoints;
    private WeakMap $fields;
    private WeakMap $meta;
    private WeakMap $links;
    private ArrayObject $resourceLinkDefinitions;
    private ArrayObject $relationshipLinkDefinitions;

    public function __construct(public JsonApi $api)
    {
        $this->endpoints = new WeakMap();
        $this->fields = new WeakMap();
        $this->meta = new WeakMap();
        $this->links = new WeakMap();
        $this->resourceLinkDefinitions = new ArrayObject();
        $this->relationshipLinkDefinitions = new ArrayObject();
    }

    /**
     * Get a resource by its type.
     */
    public function resource(string $type): Resource
    {
        return $this->api->getResource($type);
    }

    public function endpoints(Collection $collection): array
    {
        return $this->endpoints[$collection] ??= $collection->endpoints();
    }

    /**
     * Get the fields for the given resource, keyed by name.
     *
     * @return array<string, Field>
     */
    public function fields(Resource $resource): array
    {
        return $this->namedDefinitions($this->fields, $resource, $resource->fields());
    }

    /**
     * Get the meta fields for the given resource, keyed by name.
     *
     * @return array<string, Meta>
     */
    public function meta(Resource $resource): array
    {
        return $this->namedDefinitions($this->meta, $resource, $resource->meta());
    }

    /**
     * Get the link fields for the given resource, keyed by name.
     *
     * @return array<string, Link>
     */
    public function links(Resource $resource): array
    {
        return $this->namedDefinitions($this->links, $resource, $resource->links());
    }

    /**
     * Cache resource link definitions per request or schema generation run.
     *
     * @return array<string, array{0: object, 1: Collection}>
     */
    public function resourceLinkDefinitions(): array
    {
        $type = $this->resource->type();

        if (isset($this->resourceLinkDefinitions[$type])) {
            return $this->resourceLinkDefinitions[$type];
        }

        $definitions = $this->collectResourceLinkDefinitions(
            $this->api->getResourceCollections($type),
        );

        return $this->resourceLinkDefinitions[$type] = $definitions;
    }

    /**
     * Cache relationship link definitions per request or schema generation run.
     *
     * @return array<string, object>
     */
    public function relationshipLinkDefinitions(Relationship $relationship): array
    {
        $type = $this->resource->type();
        $collections = $this->collection ? [$this->collection] : $this->api->getResourceCollections($type);
        $key = implode("\0", [
            $this->collection?->name() ?? '*',
            $type,
            $relationship->name,
        ]);

        if (isset($this->relationshipLinkDefinitions[$key])) {
            return $this->relationshipLinkDefinitions[$key];
        }

        $definitions = $this->collectRelationshipLinkDefinitions(
            $collections,
            $relationship,
        );

        return $this->relationshipLinkDefinitions[$key] = $definitions;
    }

    private function namedDefinitions(WeakMap $cache, object $resource, iterable $definitions): array
    {
        if (isset($cache[$resource])) {
            return $cache[$resource];
        }

        $namedDefinitions = [];

        foreach ($definitions as $definition) {
            $namedDefinitions[$definition->name] = $definition;
        }

        return $cache[$resource] = $namedDefinitions;
    }

    private function collectResourceLinkDefinitions(array $collections): array
    {
        $definitions = [];

        foreach ($collections as $collection) {
            $context = $this->withCollection($collection);

            foreach ($this->endpoints($collection) as $endpoint) {
                if (!$endpoint instanceof ProvidesResourceLinks) {
                    continue;
                }

                foreach ($endpoint->resourceLinks($context) as $field) {
                    $definitions[$field->name] ??= [$field, $collection];
                }
            }
        }

        return $definitions;
    }

    private function collectRelationshipLinkDefinitions(
        array $collections,
        Relationship $relationship,
    ): array {
        $definitions = [];

        foreach ($collections as $collection) {
            $context = $this->withCollection($collection);

            foreach ($this->endpoints($collection) as $endpoint) {
                if (!$endpoint instanceof ProvidesRelationshipLinks) {
                    continue;
                }

                foreach ($endpoint->relationshipLinks($relationship, $context) as $field) {
                    $definitions[$field->name] ??= $field;
                }
            }
        }

        return $definitions;
    }

    public function withCollection(?Collection $collection): static
    {
        $new = clone $this;
        $new->collection = $collection;
        return $new;
    }

    public function withResource(?Resource $resource): static
    {
        $new = clone $this;
        $new->resource = $resource;
        return $new;
    }

    public function withEndpoint(?Endpoint $endpoint): static
    {
        $new = clone $this;
        $new->endpoint = $endpoint;
        return $new;
    }

    public function withField(?Field $field): static
    {
        $new = clone $this;
        $new->field = $field;
        return $new;
    }
}
