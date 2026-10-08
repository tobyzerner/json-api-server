<?php

namespace Tobyz\JsonApiServer;

use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Endpoint\Concerns\EndpointDispatcher;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\InternalServerErrorException;
use Tobyz\JsonApiServer\Exception\JsonApiErrorsException;
use Tobyz\JsonApiServer\Exception\NotFoundException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Tobyz\JsonApiServer\Schema\Parameter;

class JsonApi implements RequestHandlerInterface
{
    use HasMeta;
    use EndpointDispatcher;

    public const MEDIA_TYPE = 'application/vnd.api+json';
    public const VERSION = '1.1';

    /**
     * @var Extension[]
     */
    public array $extensions = [];

    /**
     * @var Resource[]
     */
    public array $resources = [];

    /**
     * @var Collection[]
     */
    public array $collections = [];

    public array $errors = [];

    /**
     * @var Parameter[]
     */
    protected array $parameters = [];

    /**
     * @var array<string, Collection[]>
     */
    private array $collectionsByResource = [];

    public function __construct(
        public string $basePath = '',
    ) {
        $this->basePath = rtrim($this->basePath, '/');
    }

    /**
     * Define shared request parameters. Built-in query families belong to endpoints.
     *
     * @param Parameter[] $parameters
     */
    public function parameters(array $parameters): static
    {
        foreach ($parameters as $parameter) {
            if (
                $parameter->in === 'query'
                && preg_match('/^(page|filter|sort|include|fields)(\[|$)/', $parameter->name)
            ) {
                throw new InvalidArgumentException(
                    "Query parameter '$parameter->name' is reserved for endpoint configuration.",
                );
            }
        }

        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * Merge API, extension, and endpoint parameters by location and name, with later
     * definitions winning.
     *
     * @param Parameter[] $parameters
     * @param Extension[] $extensions
     * @return Parameter[]
     */
    public function getParameters(array $parameters = [], array $extensions = []): array
    {
        $merged = [];

        foreach ([
            $this->parameters,
            ...array_map(fn(Extension $extension) => $extension->parameters(), $extensions),
            $parameters,
        ] as $group) {
            foreach ($group as $parameter) {
                $merged[$parameter->key()] = $parameter;
            }
        }

        return array_values($merged);
    }

    /**
     * Register an extension.
     */
    public function extension(Extension $extension): void
    {
        $namespace = $extension->namespace();

        if ($namespace !== null) {
            if (!preg_match('/^[a-zA-Z0-9]+\z/', $namespace)) {
                throw new InvalidArgumentException(
                    "Extension namespace '$namespace' must contain only alphanumeric characters.",
                );
            }

            foreach ($this->extensions as $registered) {
                if (
                    $registered->namespace() === $namespace
                    && $registered->uri() !== $extension->uri()
                ) {
                    throw new InvalidArgumentException(
                        "Extension namespace '$namespace' is already used by {$registered->uri()}.",
                    );
                }
            }
        }

        foreach ($extension->parameters() as $parameter) {
            if (
                $parameter->in === 'query'
                && ($namespace === null || !str_starts_with($parameter->name, "$namespace:"))
            ) {
                throw new InvalidArgumentException(
                    "Extension query parameter '$parameter->name' must be prefixed with the extension's namespace.",
                );
            }
        }

        $this->extensions[$extension->uri()] = $extension;
    }

    /**
     * Define a new collection.
     */
    public function collection(Collection $collection): void
    {
        $this->collections[$collection->name()] = $collection;

        foreach ($collection->resources() as $type) {
            $this->collectionsByResource[$type][] = $collection;
        }
    }

    /**
     * Define a new resource.
     */
    public function resource(Resource $resource): void
    {
        $this->resources[$resource->type()] = $resource;

        if ($resource instanceof Collection) {
            $this->collection($resource);
        }
    }

    /**
     * Get a collection by name.
     *
     * @throws ResourceNotFoundException if the collection has not been defined.
     */
    public function getCollection(string $type): Collection
    {
        if (!isset($this->collections[$type])) {
            throw new ResourceNotFoundException($type);
        }

        return $this->collections[$type];
    }

    /**
     * Get collections that contain the given resource type.
     *
     * @return Collection[]
     */
    public function getResourceCollections(string $type): array
    {
        return $this->collectionsByResource[$type] ?? [];
    }

    /**
     * Get a resource by type.
     *
     * @throws ResourceNotFoundException if the resource has not been defined.
     */
    public function getResource(string $type): Resource
    {
        if (!isset($this->resources[$type])) {
            throw new ResourceNotFoundException($type);
        }

        return $this->resources[$type];
    }

    public function errors(array $errors): static
    {
        $this->errors = array_replace_recursive($this->errors, $errors);

        return $this;
    }

    /**
     * Handle a request.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = new Context($this, $request);

        $response = null;

        foreach ($context->negotiatedExtensions() as $extension) {
            if ($response = $extension->handle($context)) {
                if (!$response->hasHeader('Content-Type')) {
                    $response = $response->withHeader('Content-Type', self::MEDIA_TYPE);
                }

                $context->activateExtension($extension->uri());
                break;
            }
        }

        if (!$response) {
            $segments = $context->pathSegments();

            if (!$segments) {
                throw new NotFoundException();
            }

            $collectionName = array_shift($segments);
            $collection = $this->getCollection($collectionName);

            $context = $context->withCollection($collection)->withPathSegments($segments);

            $response = $this->dispatchEndpoints($collection->endpoints(), $context);
        }

        if (!$response) {
            throw new NotFoundException();
        }

        $response = $this->withMediaTypeParameters($response, $context);

        return $response->withAddedHeader('Vary', 'Accept');
    }

    /**
     * Add the active extensions and profiles to a JSON:API response's media type,
     * merging them with any the response already declares.
     */
    private function withMediaTypeParameters(
        ResponseInterface $response,
        Context $context,
    ): ResponseInterface {
        $extensions = array_keys(array_filter($context->activeExtensions->getArrayCopy()));
        $profiles = array_keys(array_filter($context->activeProfiles->getArrayCopy()));

        if (!$extensions && !$profiles) {
            return $response;
        }

        $type = JsonApiMediaType::parse($response->getHeaderLine('Content-Type'), strict: false);

        if (!$type) {
            return $response;
        }

        return $response->withHeader('Content-Type', (string) $type->with($extensions, $profiles));
    }

    /**
     * Convert an exception into a JSON:API error document response.
     */
    public function error($e): ResponseInterface
    {
        if ($e instanceof JsonApiErrorsException) {
            $errors = $e->errors;
        } else {
            if (!$e instanceof ErrorProvider) {
                $e = new InternalServerErrorException();
            }
            $errors = [$e];
        }

        $status = $e->getJsonApiStatus();
        $context = new Context($this, new ServerRequest('GET', '/'));

        return $context->createResponse(['errors' => array_map(
            $this->formatError(...),
            $errors,
        )])->withStatus($status);
    }

    private function formatError(ErrorProvider $exception): array
    {
        $error = $exception->getJsonApiError();
        $class = get_class($exception);

        if (isset($this->errors[$class])) {
            $error = array_replace_recursive($error, $this->errors[$class]);
        }

        if (isset($error['detail']) && isset($error['meta'])) {
            $replacements = [];

            foreach ($error['meta'] as $key => $value) {
                if (is_scalar($value) || $value instanceof \Stringable) {
                    $replacements[':' . $key] = (string) $value;
                }
            }

            $error['detail'] = strtr($error['detail'], $replacements);
        }

        return $error;
    }

    /**
     * Strip the API base path from the start of the given path.
     */
    public function stripBasePath(string $path): string
    {
        $basePath = parse_url($this->basePath, PHP_URL_PATH) ?: '';

        $len = strlen($basePath);

        if (substr($path, 0, $len) === $basePath) {
            $path = substr($path, $len);
        }

        return $path;
    }
}
