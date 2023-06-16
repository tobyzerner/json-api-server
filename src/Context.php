<?php

namespace Tobyz\JsonApiServer;

use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Endpoint\EndpointInterface;
use Tobyz\JsonApiServer\Resource\ResourceInterface;
use Tobyz\JsonApiServer\Schema\Field\Field;
use WeakMap;

class Context
{
    public ?ResourceInterface $resource = null;
    public ?EndpointInterface $endpoint = null;
    public ?Serializer $serializer = null;
    public mixed $model = null;
    public ?Field $field = null;
    public ?array $include = null;

    private ?array $body;
    private string $path;

    private WeakMap $fields;
    private WeakMap $sparseFields;

    public function __construct(public JsonApi $api, public ServerRequestInterface $request)
    {
        $this->fields = new WeakMap();
        $this->sparseFields = new WeakMap();
    }

    /**
     * Get the value of a query param.
     */
    public function queryParam(string $name, $default = null)
    {
        return $this->request->getQueryParams()[$name] ?? $default;
    }

    /**
     * Get the request method.
     */
    public function method(): string
    {
        return $this->request->getMethod();
    }

    /**
     * Get the request path relative to the API base path.
     */
    public function path(): string
    {
        return $this->path ??= trim(
            $this->api->stripBasePath($this->request->getUri()->getPath()),
            '/',
        );
    }

    /**
     * Get the parsed JSON:API payload.
     */
    public function body(): ?array
    {
        return $this->body ??=
            (array) $this->request->getParsedBody() ?:
            json_decode($this->request->getBody()->getContents(), true);
    }

    /**
     * Get a resource by its type.
     */
    public function resource(string $type): ResourceInterface
    {
        return $this->api->getResource($type);
    }

    /**
     * Get the fields for the given resource, keyed by name.
     *
     * @return array<string, Field>
     */
    public function fields(ResourceInterface $resource): array
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
     *
     */
    public function sparseFields(ResourceInterface $resource): array
    {
        if (isset($this->sparseFields[$resource])) {
            return $this->sparseFields[$resource];
        }

        $fields = $this->fields($resource);

        if ($requested = $this->queryParam('fields')[$resource->type()] ?? null) {
            $requested = is_array($requested) ? $requested : explode(',', $requested);

            $fields = array_intersect_key($fields, array_flip($requested));
        }

        return $this->sparseFields[$resource] = $fields;
    }

    public function withRequest(ServerRequestInterface $request): static
    {
        $new = clone $this;
        $new->request = $request;
        $new->sparseFields = new WeakMap();
        $new->body = null;
        $new->path = null;
        return $new;
    }

    public function withResource(ResourceInterface $resource): static
    {
        $new = clone $this;
        $new->resource = $resource;
        return $new;
    }

    public function withEndpoint(EndpointInterface $endpoint): static
    {
        $new = clone $this;
        $new->endpoint = $endpoint;
        return $new;
    }

    public function withSerializer(Serializer $serializer): static
    {
        $new = clone $this;
        $new->serializer = $serializer;
        return $new;
    }

    public function withModel(mixed $model): static
    {
        $new = clone $this;
        $new->model = $model;
        return $new;
    }

    public function withField(Field $field): static
    {
        $new = clone $this;
        $new->field = $field;
        return $new;
    }

    public function withInclude(?array $include): static
    {
        $new = clone $this;
        $new->include = $include;
        return $new;
    }
}
