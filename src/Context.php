<?php

namespace Tobyz\JsonApiServer;

use ArrayObject;
use HttpAccept\AcceptParser;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\Field\InvalidFieldValueException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\Request\InvalidQueryParameterException;
use Tobyz\JsonApiServer\Exception\Request\InvalidSparseFieldsetsException;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Parameter;
use WeakMap;

class Context extends SchemaContext
{
    public ?object $query = null;
    public ?Serializer $serializer = null;
    public ?object $model = null;
    public ?array $data = null;
    public ?array $include = null;
    public ArrayObject $documentMeta;
    public ArrayObject $documentLinks;
    public ArrayObject $activeProfiles;
    public WeakMap $resourceMeta;

    private ?array $body;
    private ?string $path;
    private ?array $pathSegments = null;
    private ?array $requestedExtensions = null;
    private ?array $requestedProfiles = null;
    private array $parameters = [];

    private WeakMap $resourceIds;
    private WeakMap $modelIds;
    private WeakMap $sparseFields;

    public function __construct(JsonApi $api, public ServerRequestInterface $request)
    {
        parent::__construct($api);

        $this->parseAcceptHeader();

        $this->resourceIds = new WeakMap();
        $this->modelIds = new WeakMap();
        $this->sparseFields = new WeakMap();

        $this->documentMeta = new ArrayObject();
        $this->documentLinks = new ArrayObject();
        $this->activeProfiles = new ArrayObject();

        $this->resourceMeta = new WeakMap();
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

    public function pathSegments(): array
    {
        return $this->pathSegments ??= array_values(
            array_filter(explode('/', trim($this->path(), '/'))),
        );
    }

    public function withPathSegments(array $segments): static
    {
        $new = clone $this;
        $new->pathSegments = array_values($segments);

        return $new;
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

    public function id(Resource $resource, $model): string
    {
        if (isset($this->modelIds[$model])) {
            return $this->modelIds[$model];
        }

        $id = $this->resourceIds[$resource] ??= $resource->id();

        return $this->modelIds[$model] = $id->serializeValue($id->getValue($this), $this);
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
        $fieldsParam = $this->parameter('fields');

        if (is_array($fieldsParam) && array_key_exists($type, $fieldsParam)) {
            $requested = $fieldsParam[$type];

            if (!is_string($requested)) {
                throw (new InvalidSparseFieldsetsException())->source([
                    'parameter' => "fields[$type]",
                ]);
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
        if ($sort = $this->parameter('sort')) {
            foreach (parse_sort_string($sort) as [$name, $direction]) {
                if ($name === $field) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether a profile has been requested.
     */
    public function profileRequested(string $uri): bool
    {
        return in_array($uri, $this->requestedProfiles());
    }

    /**
     * Get all requested profile URIs.
     *
     * @return array
     */
    public function requestedProfiles(): array
    {
        if ($this->requestedProfiles === null) {
            $this->parseAcceptHeader();
        }

        return $this->requestedProfiles;
    }

    /**
     * Get all requested extension URIs from Accept header.
     *
     * @return array
     */
    public function requestedExtensions(): array
    {
        if ($this->requestedExtensions === null) {
            $this->parseAcceptHeader();
        }

        return $this->requestedExtensions;
    }

    private function parseAcceptHeader(): void
    {
        $accept = $this->request->getHeaderLine('Accept');

        if (!$accept) {
            $this->requestedProfiles = [];
            $this->requestedExtensions = [];
            return;
        }

        $list = (new AcceptParser())->parse($accept);

        foreach ($list as $mediaType) {
            if (!in_array($mediaType->name(), [$this->api::MEDIA_TYPE, '*/*'])) {
                continue;
            }

            if (array_diff(array_keys($mediaType->parameters()), ['ext', 'profile'])) {
                continue;
            }

            $extensionUris = $mediaType->hasParamater('ext')
                ? explode(' ', $mediaType->getParameter('ext'))
                : [];

            if (array_diff($extensionUris, array_keys($this->api->extensions))) {
                continue;
            }

            $profileUris = $mediaType->hasParamater('profile')
                ? explode(' ', $mediaType->getParameter('profile'))
                : [];

            $this->requestedProfiles = $profileUris;
            $this->requestedExtensions = $extensionUris;
            return;
        }

        throw new NotAcceptableException();
    }

    public function withRequest(ServerRequestInterface $request): static
    {
        $new = clone $this;
        $new->request = $request;
        $new->sparseFields = new WeakMap();
        $new->body = null;
        $new->path = null;
        $new->pathSegments = null;
        $new->requestedProfiles = null;
        $new->requestedExtensions = null;
        $new->parseAcceptHeader();
        return $new;
    }

    public function withBody(?array $body): static
    {
        $new = clone $this;
        $new->body = $body;
        return $new;
    }

    public function withData(?array $data): static
    {
        $new = clone $this;
        $new->data = $data;
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

    public function activateProfile(string $uri): static
    {
        $this->activeProfiles[$uri] = true;

        return $this;
    }

    /**
     * Load and validate parameters from the request.
     *
     * @param Parameter[] $parameters
     */
    public function withParameters(array $parameters): static
    {
        $context = clone $this;
        $context->parameters = [];

        $this->validateQueryParameters(
            array_filter($parameters, fn(Parameter $p) => $p->in === 'query'),
        );

        $errors = [];

        foreach ($parameters as $parameter) {
            $value = $this->extractParameterValue($parameter);

            if ($value === null && $parameter->default) {
                $value = ($parameter->default)();
            }

            $value = $parameter->deserializeValue($value, $context);

            if ($value === null && !$parameter->required) {
                continue;
            }

            $fail = function ($error = []) use (&$errors, $parameter) {
                if (!$error instanceof ErrorProvider) {
                    $error = new InvalidFieldValueException(
                        is_scalar($error) ? ['detail' => (string) $error] : $error,
                    );
                }

                $errors[] = $error->source(['parameter' => $parameter->name]);
            };

            $parameter->validateValue($value, $fail, $context);

            $context->parameters[$parameter->name] = $value;
        }

        if ($errors) {
            throw new JsonApiErrorsException($errors);
        }

        return $context;
    }

    /**
     * Get a validated parameter value.
     */
    public function parameter(string $name): mixed
    {
        return $this->parameters[$name] ?? null;
    }

    private function validateQueryParameters(array $parameters): void
    {
        foreach ($this->request->getQueryParams() as $key => $value) {
            if (!ctype_lower($key)) {
                continue;
            }

            foreach ($this->flattenQueryParameters([$key => $value]) as $flattenedKey => $v) {
                $matched = false;

                foreach ($parameters as $parameter) {
                    if (
                        $flattenedKey === $parameter->name ||
                        str_starts_with($flattenedKey, $parameter->name . '[')
                    ) {
                        $matched = true;
                    }
                }

                if (!$matched) {
                    throw new InvalidQueryParameterException($flattenedKey);
                }
            }
        }
    }

    private function flattenQueryParameters(array $params, string $prefix = ''): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            $newKey = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result += $this->flattenQueryParameters($value, $newKey);
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    private function extractParameterValue(Parameter $param): mixed
    {
        return match ($param->in) {
            'query' => $this->getNestedQueryParam($param->name),
            'header' => $this->request->getHeaderLine($param->name) ?: null,
            default => null,
        };
    }

    private function getNestedQueryParam(string $name): mixed
    {
        $value = $this->request->getQueryParams();

        preg_match_all('/[^\[\]]+/', $name, $matches);

        foreach ($matches[0] ?? [] as $segment) {
            $value = $value[$segment] ?? null;
        }

        return $value;
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

    /**
     * Create a JSON:API response.
     */
    public function createResponse(array $document = []): ResponseInterface
    {
        $response = (new Response())->withHeader('Content-Type', $this->api::MEDIA_TYPE);

        if ($document) {
            $jsonapi = ['version' => $this->api::VERSION];

            if ($meta = $this->api->serializeMeta($this)) {
                $jsonapi['meta'] = $meta;
            }

            $document += ['jsonapi' => $jsonapi];

            if ($meta = $this->documentMeta->getArrayCopy()) {
                $document['meta'] = array_merge($document['meta'] ?? [], $meta);
            }

            if ($links = $this->documentLinks->getArrayCopy()) {
                $document['links'] = array_merge($document['links'] ?? [], $links);
            }

            $response = $response->withBody(
                Stream::create(
                    json_encode(
                        $document,
                        JSON_HEX_TAG |
                            JSON_HEX_APOS |
                            JSON_HEX_AMP |
                            JSON_HEX_QUOT |
                            JSON_UNESCAPED_SLASHES,
                    ),
                ),
            );
        }

        return $response;
    }
}
