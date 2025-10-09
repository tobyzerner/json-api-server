<?php

namespace Tobyz\JsonApiServer;

use HttpAccept\ContentTypeParser;
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
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;

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
     * @var array<string, Collection[]>
     */
    private array $collectionsByResource = [];

    public function __construct(public string $basePath = '')
    {
        $this->basePath = rtrim($this->basePath, '/');
    }

    /**
     * Register an extension.
     */
    public function extension(Extension $extension): void
    {
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

        $response = $this->runExtensions($context);

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

        if (count($context->activeProfiles)) {
            $contentType = $response->getHeaderLine('Content-Type');

            if (str_starts_with($contentType, self::MEDIA_TYPE)) {
                $profileUris = array_keys(array_filter($context->activeProfiles->getArrayCopy()));
                $contentType .= '; profile="' . implode(' ', $profileUris) . '"';
                $response = $response->withHeader('Content-Type', $contentType);
            }
        }

        return $response->withAddedHeader('Vary', 'Accept');
    }

    private function runExtensions(Context $context): ?ResponseInterface
    {
        $contentTypeExtensionUris = $this->getContentTypeExtensionUris($context->request);
        $acceptExtensionUris = $context->requestedExtensions();

        $activeExtensions = array_intersect_key(
            $this->extensions,
            array_flip($contentTypeExtensionUris),
            array_flip($acceptExtensionUris),
        );

        foreach ($activeExtensions as $extension) {
            if ($response = $extension->handle($context)) {
                return $response->withHeader(
                    'Content-Type',
                    self::MEDIA_TYPE . '; ext=' . $extension->uri(),
                );
            }
        }

        return null;
    }

    private function getContentTypeExtensionUris(ServerRequestInterface $request): array
    {
        if (!($contentType = $request->getHeaderLine('Content-Type'))) {
            return [];
        }

        try {
            $type = (new ContentTypeParser())->parse($contentType);
        } catch (InvalidArgumentException) {
            throw new UnsupportedMediaTypeException();
        }

        if ($type->name() !== JsonApi::MEDIA_TYPE) {
            throw new UnsupportedMediaTypeException();
        }

        if (!empty(array_diff(array_keys($type->parameters()), ['ext', 'profile']))) {
            throw new UnsupportedMediaTypeException();
        }

        $extensionUris = $type->hasParamater('ext') ? explode(' ', $type->getParameter('ext')) : [];

        if (!empty(array_diff($extensionUris, array_keys($this->extensions)))) {
            throw new UnsupportedMediaTypeException();
        }

        return $extensionUris;
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

        return $context
            ->createResponse(['errors' => array_map($this->formatError(...), $errors)])
            ->withStatus($status);
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
                $replacements[':' . $key] = (string) $value;
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
