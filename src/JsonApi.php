<?php

namespace Tobyz\JsonApiServer;

use HttpAccept\AcceptParser;
use HttpAccept\ContentTypeParser;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\ErrorProvider;
use Tobyz\JsonApiServer\Exception\InternalServerErrorException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\NotFoundException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Resource\Collection;
use Tobyz\JsonApiServer\Resource\Resource;

class JsonApi implements RequestHandlerInterface
{
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

    /**
     * Handle a request.
     *
     * @throws UnsupportedMediaTypeException if the request Content-Type header is invalid
     * @throws NotAcceptableException if the request Accept header is invalid
     * @throws MethodNotAllowedException if the request method is invalid
     * @throws BadRequestException if the request URI is invalid
     */
    public function handle(Request $request): Response
    {
        $this->validateQueryParameters($request);

        $context = new Context($this, $request);

        $response = $this->runExtensions($context);

        if (!$response) {
            $segments = explode('/', trim($context->path(), '/'), 2);

            $context = $context->withCollection($this->getCollection($segments[0]));

            foreach ($context->collection->endpoints() as $endpoint) {
                try {
                    if ($response = $endpoint->handle($context->withEndpoint($endpoint))) {
                        break;
                    }
                } catch (MethodNotAllowedException $e) {
                    // Give other endpoints a chance to handle
                }
            }
        }

        if (!$response) {
            throw $e ?? new NotFoundException();
        }

        return $response->withAddedHeader('Vary', 'Accept');
    }

    private function runExtensions(Context $context): ?Response
    {
        $request = $context->request;

        $contentTypeExtensionUris = $this->getContentTypeExtensionUris($request);
        $acceptableExtensionUris = $this->getAcceptableExtensionUris($request);

        $activeExtensions = array_intersect_key(
            $this->extensions,
            array_flip($contentTypeExtensionUris),
            array_flip($acceptableExtensionUris),
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

    private function validateQueryParameters(Request $request): void
    {
        foreach ($request->getQueryParams() as $key => $value) {
            if (
                !preg_match('/[^a-z]/', $key) &&
                !in_array($key, ['include', 'fields', 'filter', 'page', 'sort'])
            ) {
                throw (new BadRequestException("Invalid query parameter: $key"))->setSource([
                    'parameter' => $key,
                ]);
            }
        }
    }

    private function getContentTypeExtensionUris(Request $request): array
    {
        if (!($contentType = $request->getHeaderLine('Content-Type'))) {
            return [];
        }

        try {
            $type = (new ContentTypeParser())->parse($contentType);
        } catch (InvalidArgumentException $e) {
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

    private function getAcceptableExtensionUris(Request $request): array
    {
        if (!($accept = $request->getHeaderLine('Accept'))) {
            return [];
        }

        $list = (new AcceptParser())->parse($accept);

        foreach ($list as $mediaType) {
            if (!in_array($mediaType->name(), [JsonApi::MEDIA_TYPE, '*/*'])) {
                continue;
            }

            if (!empty(array_diff(array_keys($mediaType->parameters()), ['ext', 'profile']))) {
                continue;
            }

            $extensionUris = $mediaType->hasParamater('ext')
                ? explode(' ', $mediaType->getParameter('ext'))
                : [];

            if (!empty(array_diff($extensionUris, array_keys($this->extensions)))) {
                continue;
            }

            return $extensionUris;
        }

        throw new NotAcceptableException();
    }

    /**
     * Convert an exception into a JSON:API error document response.
     *
     * If the exception is not an instance of ErrorProviderInterface, an
     * Internal Server Error response will be produced.
     */
    public function error($e): Response
    {
        if (!$e instanceof ErrorProvider) {
            $e = new InternalServerErrorException();
        }

        $errors = $e->getJsonApiErrors();
        $status = $e->getJsonApiStatus();

        return json_api_response(['errors' => $errors], $status);
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
