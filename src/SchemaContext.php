<?php

namespace Tobyz\JsonApiServer;

use Tobyz\JsonApiServer\Endpoint\Endpoint;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
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

    public function __construct(public JsonApi $api)
    {
        $this->endpoints = new WeakMap();
        $this->fields = new WeakMap();
        $this->meta = new WeakMap();
        $this->links = new WeakMap();
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
        if (isset($this->fields[$resource])) {
            return $this->fields[$resource];
        }

        $fields = [];

        foreach ($resource->fields() as $field) {
            $fields[$field->name] = $field;
        }

        return $this->fields[$resource] = $fields;
    }

    /**
     * Get the meta fields for the given resource, keyed by name.
     *
     * @return array<string, Meta>
     */
    public function meta(Resource $resource): array
    {
        if (isset($this->meta[$resource])) {
            return $this->meta[$resource];
        }

        $fields = [];

        foreach ($resource->meta() as $field) {
            $fields[$field->name] = $field;
        }

        return $this->meta[$resource] = $fields;
    }

    /**
     * Get the link fields for the given resource, keyed by name.
     *
     * @return array<string, Link>
     */
    public function links(Resource $resource): array
    {
        if (isset($this->links[$resource])) {
            return $this->links[$resource];
        }

        $fields = [];

        foreach ($resource->links() as $field) {
            $fields[$field->name] = $field;
        }

        return $this->links[$resource] = $fields;
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
