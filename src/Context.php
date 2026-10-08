<?php

namespace Tobyz\JsonApiServer;

use ArrayObject;
use HttpAccept\AcceptParser;
use JsonException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Tobyz\JsonApiServer\Exception\Data\InvalidJsonException;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\Field\InvalidFieldValueException;
use Tobyz\JsonApiServer\Exception\Field\RequiredFieldException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\Request\InvalidQueryParameterException;
use Tobyz\JsonApiServer\Exception\Request\InvalidSparseFieldsetsException;
use Tobyz\JsonApiServer\Exception\Sourceable;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Listable;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Field\Field;
use Tobyz\JsonApiServer\Schema\Field\Relationship;
use Tobyz\JsonApiServer\Schema\Parameter;
use WeakMap;

class Context extends SchemaContext
{
    public ?object $query = null;
    public ?Serializer $serializer = null;
    public ?object $model = null;
    public ?array $data = null;
    public ?array $include = null;
    public bool $linkageOnly = false;
    public ArrayObject $documentMeta;
    public ArrayObject $documentLinks;
    public ArrayObject $activeProfiles;
    public ArrayObject $activeExtensions;
    public WeakMap $resourceMeta;

    private ?array $body;
    private ?string $path;
    private ?array $pathSegments = null;
    private ?array $requestedExtensions = null;
    private ?array $requestedProfiles = null;
    private array $parameters = [];
    private ?array $activeFilters = null;
    private WeakMap $normalizedFilters;

    private WeakMap $resourceIds;
    /** @var WeakMap<Resource, WeakMap<object, string>> */
    private WeakMap $modelIds;
    private WeakMap $sparseFields;

    /** @var array<string, Extension>|null */
    private ?array $negotiatedExtensions = null;

    public function __construct(
        JsonApi $api,
        public ServerRequestInterface $request,
    ) {
        parent::__construct($api);

        $this->parseAcceptHeader();

        $this->resourceIds = new WeakMap();
        $this->modelIds = new WeakMap();
        $this->sparseFields = new WeakMap();
        $this->normalizedFilters = new WeakMap();

        $this->documentMeta = new ArrayObject();
        $this->documentLinks = new ArrayObject();
        $this->activeProfiles = new ArrayObject();
        $this->activeExtensions = new ArrayObject();

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

        return (
            $this->api->basePath . '/' . $this->path() . ($queryString ? '?' . $queryString : '')
        );
    }

    /**
     * Get the parsed JSON:API payload.
     */
    public function body(): ?array
    {
        try {
            return $this->body ??= (array) $this->request->getParsedBody() ?: json_decode(
                $this->request->getBody()->getContents(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new InvalidJsonException($e->getMessage());
        }
    }

    public function id(Resource $resource, $model): string
    {
        $modelIds = $this->modelIds[$resource] ??= new WeakMap();

        if (isset($modelIds[$model])) {
            return $modelIds[$model];
        }

        $id = $this->resourceIds[$resource] ??= $resource->id();

        return $modelIds[$model] = $id->serializeValue($id->getValue($this), $this);
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

        $names = null;

        foreach ($this->negotiatedExtensions() as $extension) {
            if (($names = $extension->sparseFields($resource, $this)) !== null) {
                $this->activateExtension($extension->uri());
                break;
            }
        }

        $type = $resource->type();
        $fieldsParam = $this->parameter('fields');

        if ($names === null && is_array($fieldsParam) && array_key_exists($type, $fieldsParam)) {
            $requested = $fieldsParam[$type];

            if (!is_string($requested)) {
                throw (new InvalidSparseFieldsetsException())->source([
                    'parameter' => "fields[$type]",
                ]);
            }

            $names = explode(',', $requested);
        }

        return $this->sparseFields[$resource] = $names === null
            ? $this->defaultFields($resource)
            : array_intersect_key($this->fields($resource), array_flip($names));
    }

    /**
     * Get the fields included for the given resource when no sparse fieldset is
     * requested, keyed by name.
     *
     * @return array<string, Field>
     */
    public function defaultFields(Resource $resource): array
    {
        return array_filter($this->fields($resource), fn(Field $field) => !$field->isSparse($this));
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

    /**
     * Get the registered extensions negotiated for the request, keyed by URI.
     *
     * Extensions must be requested in the Accept header and, if the request has
     * a body, in the Content-Type header.
     *
     * @return array<string, Extension>
     */
    public function negotiatedExtensions(): array
    {
        if ($this->negotiatedExtensions !== null) {
            return $this->negotiatedExtensions;
        }

        $uris = $this->requestedExtensions();

        if ($this->request->hasHeader('Content-Type')) {
            $contentTypeUris = $this->parseContentTypeExtensions();

            // Extensions only need to be declared in the Content-Type when there
            // is a request body for them to apply to.
            if ($this->hasBody()) {
                $uris = array_intersect($uris, $contentTypeUris);
            }
        }

        return $this->negotiatedExtensions = array_intersect_key(
            $this->api->extensions,
            array_flip($uris),
        );
    }

    private function hasBody(): bool
    {
        return (
            $this->request->getParsedBody()
            || $this->request->getBody()->getSize() > 0
            || (int) $this->request->getHeaderLine('Content-Length') > 0
            || $this->request->hasHeader('Transfer-Encoding')
        );
    }

    private function parseContentTypeExtensions(): array
    {
        if (!($contentType = $this->request->getHeaderLine('Content-Type'))) {
            return [];
        }

        $type = JsonApiMediaType::parse($contentType);

        if (!$this->isSupportedMediaType($type)) {
            throw new UnsupportedMediaTypeException();
        }

        return $type->extensions;
    }

    /**
     * Determine whether a JSON:API media type only uses registered extensions.
     */
    private function isSupportedMediaType(?JsonApiMediaType $type): bool
    {
        return $type && !array_diff($type->extensions, array_keys($this->api->extensions));
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

            $type = JsonApiMediaType::fromParameters($mediaType);

            if (!$this->isSupportedMediaType($type)) {
                continue;
            }

            $this->requestedExtensions = $type->extensions;
            $this->requestedProfiles = $type->profiles;
            return;
        }

        throw new NotAcceptableException();
    }

    public function withRequest(ServerRequestInterface $request): static
    {
        $new = clone $this;
        $new->request = $request;
        $new->parameters = [];
        $new->activeFilters = null;
        $new->normalizedFilters = new WeakMap();
        $new->sparseFields = new WeakMap();
        $new->body = null;
        $new->path = null;
        $new->pathSegments = null;
        $new->requestedProfiles = null;
        $new->requestedExtensions = null;
        $new->negotiatedExtensions = null;
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

    // Only forSerializedField() knows when a field needs linkage only, so
    // contexts derived for another field or include don't inherit the flag.
    public function withField(?Field $field): static
    {
        $new = parent::withField($field);
        $new->linkageOnly = false;
        return $new;
    }

    public function withInclude(?array $include): static
    {
        $new = clone $this;
        $new->include = $include;
        $new->linkageOnly = false;
        return $new;
    }

    public function forSerializedField(Field $field, ?array $include = null): static
    {
        $new = clone $this;
        $new->field = $field;
        $new->include = $include;
        $new->linkageOnly =
            $field instanceof Relationship && $include === null && !$field->linkageMeta;
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

    public function activateExtension(string $uri): static
    {
        $this->activeExtensions[$uri] = true;

        return $this;
    }

    /**
     * Load and validate parameters that have not already been loaded from the request.
     *
     * With allowUnknown, load inherited and supplied definitions without rejecting other
     * query parameters. Call again with the full definitions to validate those too.
     *
     * @param Parameter[] $parameters
     */
    public function withParameters(array $parameters, bool $allowUnknown = false): static
    {
        $parameters = $this->api->getParameters($parameters, $this->negotiatedExtensions());

        $context = clone $this;
        $context->activeFilters = null;
        $context->normalizedFilters = new WeakMap();
        $context->sparseFields = new WeakMap();

        if (!$allowUnknown) {
            $this->validateQueryParameters(
                array_filter($parameters, fn(Parameter $p) => $p->in === 'query'),
            );
        }

        $errors = [];

        foreach ($parameters as $parameter) {
            if (array_key_exists($parameter->name, $context->parameters[$parameter->in] ?? [])) {
                continue;
            }

            [$present, $value] = $this->extractParameterValue($parameter);
            $paramContext = $context->withField($parameter);

            if (!$present && $parameter->default) {
                $value = ($parameter->default)($paramContext);
                $present = true;
            }

            if (!$present) {
                if ($parameter->required) {
                    $errors[] = (new RequiredFieldException())->prependSourceParameter($parameter->name);
                } else {
                    $context->parameters[$parameter->in][$parameter->name] = null;
                }
                continue;
            }

            try {
                $value = $parameter->deserializeValue($value, $paramContext);
            } catch (JsonApiErrorsException $e) {
                array_push($errors, ...$e->prependSourceParameter($parameter->name)->errors);
                continue;
            } catch (Sourceable $e) {
                throw $e->prependSourceParameter($parameter->name);
            }

            $fail = function ($error = []) use (&$errors, $parameter) {
                if (!$error instanceof ErrorProvider) {
                    $error = new InvalidFieldValueException(
                        is_scalar($error) ? ['detail' => (string) $error] : $error,
                    );
                }

                $errors[] = $error->prependSourceParameter($parameter->name);
            };

            $parameter->validateValue($value, $fail, $paramContext);

            $context->parameters[$parameter->in][$parameter->name] = $value;
        }

        if ($errors) {
            throw new JsonApiErrorsException($errors);
        }

        return $context;
    }

    /**
     * Get a validated parameter value.
     */
    public function parameter(string $name, string $in = 'query'): mixed
    {
        return $this->parameters[$in][$name] ?? null;
    }

    /**
     * Get a normalized top-level filter for the collection currently being processed.
     */
    public function filter(string $name): mixed
    {
        return $this->filters()[$name] ?? null;
    }

    /**
     * Get normalized filters for the current collection. Delegated filters may
     * differ from the request's top-level filter parameter.
     */
    public function filters(): array
    {
        $filters = $this->activeFilters ?? (array) $this->parameter('filter');

        if (!$filters || !$this->collection instanceof Listable) {
            return $filters;
        }

        try {
            return $this->normalizedFilters[$this->collection] ??= (new Filterer(
                $this->collection,
                $this,
            ))->normalize($filters);
        } catch (Sourceable $e) {
            // Delegated filters receive their outer path from the calling filter.
            throw $this->activeFilters === null ? $e->prependSourceParameter('filter') : $e;
        }
    }

    /**
     * Set the filters for the collection currently being processed.
     */
    public function withFilters(array $filters): static
    {
        $new = clone $this;
        $new->activeFilters = $filters;
        $new->normalizedFilters = new WeakMap();

        return $new;
    }

    private function validateQueryParameters(array $parameters): void
    {
        $namespaces = array_filter(
            array_map(
                fn(Extension $extension) => $extension->namespace(),
                $this->negotiatedExtensions(),
            ),
        );

        foreach ($this->request->getQueryParams() as $key => $value) {
            if (!ctype_lower($key) && !in_array(strstr($key, ':', true), $namespaces, true)) {
                continue;
            }

            foreach ($this->flattenQueryParameters([$key => $value]) as $flattenedKey => $v) {
                $matched = false;

                foreach ($parameters as $parameter) {
                    if (
                        $flattenedKey === $parameter->name
                        || str_starts_with($flattenedKey, $parameter->name . '[')
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

    /**
     * @return array{bool, mixed} Whether the parameter is present, and its value.
     */
    private function extractParameterValue(Parameter $param): array
    {
        if ($param->in === 'header') {
            return [
                $this->request->hasHeader($param->name),
                $this->request->getHeaderLine($param->name),
            ];
        }

        if ($param->in !== 'query') {
            return [false, null];
        }

        $value = $this->request->getQueryParams();
        preg_match_all('/[^\[\]]+/', $param->name, $matches);

        foreach ($matches[0] ?? [] as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }

        return [true, $value];
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

        [$new->collection, $new->resource] = $this->findResource(
            $collections,
            $model,
        ) ?? throw new RuntimeException(
            'No resource type defined to represent model ' . get_class($model),
        );
        $new->model = $model;

        return $new;
    }

    /**
     * Get the resource that represents a model in one of the given
     * collections, or null if none does.
     */
    public function resourceForModel(array $collections, object $model): ?Resource
    {
        return $this->findResource($collections, $model)[1] ?? null;
    }

    /**
     * @return array{0: Collection, 1: Resource}|null
     */
    private function findResource(array $collections, object $model): ?array
    {
        foreach ($collections as $collection) {
            if (is_string($collection)) {
                $collection = $this->api->getCollection($collection);
            }

            if ($type = $collection->resource($model, $this)) {
                return [$collection, $this->api->getResource($type)];
            }
        }

        return null;
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
                        JSON_HEX_TAG
                        | JSON_HEX_APOS
                        | JSON_HEX_AMP
                        | JSON_HEX_QUOT
                        | JSON_UNESCAPED_SLASHES,
                    ),
                ),
            );
        }

        return $response;
    }
}
