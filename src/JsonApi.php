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
use Tobyz\JsonApiServer\Extension\Hook\HandleRequest;
use Tobyz\JsonApiServer\Extension\Hook\Hook;
use Tobyz\JsonApiServer\Extension\Hook\HookParameters;
use Tobyz\JsonApiServer\Extension\Hook\ParameterizedHook;
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
     * @var array<string, Hook[]>
     */
    private array $hooks = [];

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
     * Merge API and endpoint parameters by location and name, with later
     * definitions winning. Hook parameter placeholders are replaced by the
     * parameters of the matching hooks of the given extensions.
     *
     * @param (Parameter|HookParameters)[] $parameters
     * @param Extension[] $extensions
     * @return Parameter[]
     */
    public function getParameters(array $parameters = [], array $extensions = []): array
    {
        return array_column($this->resolveParameters($parameters, $extensions), 0);
    }

    /**
     * Merge parameters as `getParameters` does, also returning the URI of the
     * extension each parameter comes from, if any.
     *
     * @param (Parameter|HookParameters)[] $parameters
     * @param Extension[] $extensions
     * @return list<array{Parameter, string|null}>
     */
    public function resolveParameters(array $parameters = [], array $extensions = []): array
    {
        $resolved = [];

        foreach ([...$this->parameters, ...$parameters] as $parameter) {
            if (!$parameter instanceof HookParameters) {
                $resolved[$parameter->key()] = [$parameter, null];
                continue;
            }

            foreach ($this->getHooks($extensions, $parameter->hook) as $uri => $hook) {
                foreach ($hook->getParameters() as $hookParameter) {
                    $resolved[$hookParameter->key()] = [$hookParameter, $uri];
                }
            }
        }

        return array_values($resolved);
    }

    /**
     * Get the hooks of the given extensions, keyed by extension URI.
     *
     * @param Extension[] $extensions
     * @template T of Hook
     * @param class-string<T> $class
     * @return iterable<string, T>
     */
    public function getHooks(array $extensions, string $class): iterable
    {
        foreach ($extensions as $extension) {
            $uri = $extension->uri();

            foreach ($this->hooks[$uri] ?? [] as $hook) {
                if ($hook instanceof $class) {
                    yield $uri => $hook;
                }
            }
        }
    }

    /**
     * Register an extension.
     */
    public function extension(Extension $extension): void
    {
        $uri = $extension->uri();
        $namespace = $extension->namespace();

        if ($namespace !== null) {
            if (!preg_match('/^[a-zA-Z0-9]+\z/', $namespace)) {
                throw new InvalidArgumentException(
                    "Extension namespace '$namespace' must contain only alphanumeric characters.",
                );
            }

            foreach ($this->extensions as $registered) {
                if ($registered->namespace() === $namespace && $registered->uri() !== $uri) {
                    throw new InvalidArgumentException(
                        "Extension namespace '$namespace' is already used by {$registered->uri()}.",
                    );
                }
            }
        }

        $hooks = $extension->hooks();

        foreach ($hooks as $hook) {
            if (!$hook instanceof Hook) {
                throw new InvalidArgumentException(
                    "Extension $uri must only return Hook instances from hooks().",
                );
            }

            if (!$hook instanceof ParameterizedHook) {
                continue;
            }

            foreach ($hook->getParameters() as $parameter) {
                if (
                    $parameter->in === 'query'
                    && ($namespace === null || !str_starts_with($parameter->name, "$namespace:"))
                ) {
                    throw new InvalidArgumentException(
                        "Extension query parameter '$parameter->name' must be prefixed with the extension's namespace.",
                    );
                }
            }
        }

        $this->extensions[$uri] = $extension;
        $this->hooks[$uri] = $hooks;
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

        $response = $context->firstHookResult(HandleRequest::class, $context);

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
