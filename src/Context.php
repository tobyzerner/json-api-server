<?php

namespace Tobyz\JsonApiServer;

use ArrayObject;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Endpoint\Endpoint;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
use WeakMap;

class Context
{
    public ?Collection $collection = null;
    public ?Resource $resource = null;
    public ?Endpoint $endpoint = null;
    public ?object $query = null;
    public ?Serializer $serializer = null;
    public ?object $model = null;
    public ?Field $field = null;
    public ?array $include = null;
    public ArrayObject $documentMeta;
    public ArrayObject $documentLinks;
    public WeakMap $resourceMeta;

    private ?array $body;
    private ?string $path;

    private WeakMap $endpoints;
    private WeakMap $resourceIds;
    private WeakMap $modelIds;
    private WeakMap $fields;
    private WeakMap $sparseFields;

    public function __construct(public JsonApi $api, public ServerRequestInterface $request)
    {
        $this->endpoints = new WeakMap();
        $this->resourceIds = new WeakMap();
        $this->modelIds = new WeakMap();
        $this->fields = new WeakMap();
        $this->sparseFields = new WeakMap();

        $this->documentMeta = new ArrayObject();
        $this->documentLinks = new ArrayObject();

        $this->resourceMeta = new WeakMap();
    }

    public function translate(string $key, array $replacements = []): string
    {
        return $this->api->translator->translate($key, $replacements);
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
     * Get the URL of the current request, optionally with query parameter overrides.
     */
    public function currentUrl(array $queryParams = []): string
    {
        $queryParams = array_replace_recursive($this->request->getQueryParams(), $queryParams);

        if (isset($queryParams['filter'])) {
            foreach ($queryParams['filter'] as &$v) {
                $v = $v === null ? '' : $v;
            }
        }

        ksort($queryParams);

        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        return $this->api->basePath .
            '/' .
            $this->path() .
            ($queryString ? '?' . $queryString : '');
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
    public function resource(string $type): Resource
    {
        return $this->api->getResource($type);
    }

    public function endpoints(Collection $collection): array
    {
        return $this->endpoints[$collection] ??= $collection->endpoints();
    }

    public function id(Resource $resource, $model): string
    {
        if (isset($this->modelIds[$model])) {
            return $this->modelIds[$model];
        }

        $id = $this->resourceIds[$resource] ??= $resource->id();

        return $this->modelIds[$model] = $id->serializeValue($id->getValue($this), $this);
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
     * Get only the requested fields for the given resource, keyed by name.
     *
     * @return array<string, Field>
     */
    public function sparseFields(Resource $resource): array
    {
        if (isset($this->sparseFields[$resource])) {
            return $this->sparseFields[$resource];
        }

        $fields = $this->fields($resource);
        $type = $resource->type();
        $fieldsParam = $this->queryParam('fields');

        if (is_array($fieldsParam) && array_key_exists($type, $fieldsParam)) {
            $requested = $fieldsParam[$type];

            if (!is_string($requested)) {
                throw (new BadRequestException(
                    $this->translate('request.fields_invalid'),
                ))->setSource(['parameter' => "fields[$type]"]);
            }

            $fields = array_intersect_key($fields, array_flip(explode(',', $requested)));
        } else {
            $fields = array_filter($fields, fn(Field $field) => !$field->sparse);
        }

        return $this->sparseFields[$resource] = $fields;
    }

    /**
     * Determine whether a field has been requested in a sparse fieldset.
     */
    public function fieldRequested(string $type, string $field): bool
    {
        return isset($this->sparseFields($this->resource($type))[$field]);
    }

    /**
     * Determine whether a sort field has been requested.
     */
    public function sortRequested(string $field): bool
    {
        if ($sort = $this->queryParam('sort')) {
            foreach (parse_sort_string($sort) as [$name, $direction]) {
                if ($name === $field) {
                    return true;
                }
            }
        }

        return false;
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

    public function withBody(?array $body): static
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
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

    public function withQuery(?object $query): static
    {
        $new = clone $this;
        $new->query = $query;
        return $new;
    }

    public function withSerializer(?Serializer $serializer): static
    {
        $new = clone $this;
        $new->serializer = $serializer;
        return $new;
    }

    public function withModel(?object $model): static
    {
        $new = clone $this;
        $new->model = $model;
        return $new;
    }

    public function withField(?Field $field): static
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

    public function resourceMeta($model, array $meta): static
    {
        $this->resourceMeta[$model] = array_merge($this->resourceMeta[$model] ?? [], $meta);

        return $this;
    }

    public function forModel(array $collections, ?object $model): static
    {
        $new = clone $this;

        if (!$model) {
            $new->collection = null;
            $new->resource = null;
            $new->model = null;
            return $new;
        }

        foreach ($collections as $collection) {
            if (is_string($collection)) {
                $collection = $this->api->getCollection($collection);
            }

            if ($type = $collection->resource($model, $this)) {
                $new->collection = $collection;
                $new->resource = $this->api->getResource($type);
                $new->model = $model;
                return $new;
            }
        }

        throw new RuntimeException(
            'No resource type defined to represent model ' . get_class($model),
        );
    }
}
