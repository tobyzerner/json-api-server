<?php

/*
 * This file is part of tobyz/json-api-server.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tobyz\JsonApiServer;

use JsonApiPhp\JsonApi\ErrorDocument;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Tobyz\JsonApiServer\Adapter\AdapterInterface;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\InternalServerErrorException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\NotImplementedException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Extension\Extension;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Xynha\HttpAccept\AcceptParser;

final class JsonApi implements RequestHandlerInterface
{
    public const MEDIA_TYPE = 'application/vnd.api+json';

    use FindsResources;
    use HasMeta;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var Extension[]
     */
    private $extensions = [];

    /**
     * @var ResourceType[]
     */
    private $resourceTypes = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Register an extension.
     */
    public function extension(Extension $extension)
    {
        $this->extensions[$extension->uri()] = $extension;
    }

    /**
     * Get all registered extensions.
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Define a new resource type.
     */
    public function resourceType(string $type, AdapterInterface $adapter, callable $buildSchema = null): void
    {
        $this->resourceTypes[$type] = new ResourceType($type, $adapter, $buildSchema);
    }

    /**
     * Get defined resource types.
     *
     * @return ResourceType[]
     */
    public function getResourceTypes(): array
    {
        return $this->resourceTypes;
    }

    /**
     * Get a resource type.
     *
     * @throws ResourceNotFoundException if the resource type has not been defined.
     */
    public function getResourceType(string $type): ResourceType
    {
        if (! isset($this->resourceTypes[$type])) {
            throw new ResourceNotFoundException($type);
        }

        return $this->resourceTypes[$type];
    }

    /**
     * Handle a request.
     *
     * @throws UnsupportedMediaTypeException if the request Content-Type header is invalid
     * @throws NotAcceptableException if the request Accept header is invalid
     * @throws MethodNotAllowedException if the request method is invalid
     * @throws BadRequestException if the request URI is invalid
     * @throws NotImplementedException
     */
    public function handle(Request $request): Response
    {
        $this->validateQueryParameters($request);

        $context = new Context($this, $request);

        $response = $this->runExtensions($context);

        if (! $response) {
            $response = $this->route($context);
        }

        return $response->withAddedHeader('Vary', 'Accept');
    }

    private function runExtensions(Context $context): ?Response
    {
        $request = $context->getRequest();

        $contentTypeExtensionUris = $this->getContentTypeExtensionUris($request);
        $acceptableExtensionUris = $this->getAcceptableExtensionUris($request);

        $activeExtensions = array_intersect_key(
            $this->extensions,
            array_flip($contentTypeExtensionUris),
            array_flip($acceptableExtensionUris)
        );

        foreach ($activeExtensions as $extension) {
            if ($response = $extension->handle($context)) {
                return $response->withHeader('Content-Type', self::MEDIA_TYPE.'; ext='.$extension->uri());
            }
        }

        return null;
    }

    private function route(Context $context): Response
    {
        $segments = explode('/', trim($context->getPath(), '/'));
        $resourceType = $this->getResourceType($segments[0]);

        switch (count($segments)) {
            case 1:
                return $this->routeCollection($context, $resourceType);

            case 2:
                return $this->routeResource($context, $resourceType, $segments[1]);

            case 3:
                throw new NotImplementedException();

            case 4:
                if ($segments[2] === 'relationships') {
                    throw new NotImplementedException();
                }
        }

        throw new BadRequestException();
    }

    private function validateQueryParameters(Request $request): void
    {
        foreach ($request->getQueryParams() as $key => $value) {
            if (
                ! preg_match('/[^a-z]/', $key)
                && ! in_array($key, ['include', 'fields', 'filter', 'page', 'sort'])
            ) {
                throw (new BadRequestException('Invalid query parameter: '.$key))->setSourceParameter($key);
            }
        }
    }

    private function routeCollection(Context $context, ResourceType $resourceType): Response
    {
        switch ($context->getRequest()->getMethod()) {
            case 'GET':
                return (new Endpoint\Index())->handle($context, $resourceType);

            case 'POST':
                return (new Endpoint\Create())->handle($context, $resourceType);

            default:
                throw new MethodNotAllowedException();
        }
    }

    private function routeResource(Context $context, ResourceType $resourceType, string $resourceId): Response
    {
        $model = $this->findResource($resourceType, $resourceId, $context);

        switch ($context->getRequest()->getMethod()) {
            case 'PATCH':
                return (new Endpoint\Update())->handle($context, $resourceType, $model);

            case 'GET':
                return (new Endpoint\Show())->handle($context, $resourceType, $model);

            case 'DELETE':
                return (new Endpoint\Delete())->handle($context, $resourceType, $model);

            default:
                throw new MethodNotAllowedException();
        }
    }

    private function getContentTypeExtensionUris(Request $request): array
    {
        if (! $contentType = $request->getHeaderLine('Content-Type')) {
            return [];
        }

        $mediaList = (new AcceptParser())->parse($contentType);

        if ($mediaList->count() > 1) {
            throw new UnsupportedMediaTypeException();
        }

        $mediaType = $mediaList->preferredMedia(0);

        if ($mediaType->mimetype() !== JsonApi::MEDIA_TYPE) {
            throw new UnsupportedMediaTypeException();
        }

        $parameters = $this->parseParameters($mediaType->parameters());

        if (! empty(array_diff(array_keys($parameters), ['ext', 'profile']))) {
            throw new UnsupportedMediaTypeException();
        }

        $extensionUris = isset($parameters['ext']) ? explode(' ', $parameters['ext']) : [];

        if (! empty(array_diff($extensionUris, array_keys($this->extensions)))) {
            throw new UnsupportedMediaTypeException();
        }

        return $extensionUris;
    }

    private function getAcceptableExtensionUris(Request $request): array
    {
        if (! $accept = $request->getHeaderLine('Accept')) {
            return [];
        }

        $mediaList = (new AcceptParser())->parse($accept);
        $count = $mediaList->count();

        for ($i = 0; $i < $count; $i++) {
            $mediaType = $mediaList->preferredMedia($i);

            if (! in_array($mediaType->mimetype(), [JsonApi::MEDIA_TYPE, '*/*'])) {
                continue;
            }

            $parameters = $this->parseParameters($mediaType->parameters());

            if (! empty(array_diff(array_keys($parameters), ['ext', 'profile']))) {
                continue;
            }

            $extensionUris = isset($parameters['ext']) ? explode(' ', $parameters['ext']) : [];

            if (! empty(array_diff($extensionUris, array_keys($this->extensions)))) {
                continue;
            }

            return $extensionUris;
        }

        throw new NotAcceptableException();
    }

    private function parseParameters(array $parameters): array
    {
        return array_reduce($parameters, function ($a, $v) {
            $parts = explode('=', $v, 2);
            $a[$parts[0]] = trim($parts[1], '"');
            return $a;
        }, []);
    }

    /**
     * Convert an exception into a JSON:API error document response.
     *
     * If the exception is not an instance of ErrorProviderInterface, an
     * Internal Server Error response will be produced.
     */
    public function error($e): Response
    {
        if (! $e instanceof ErrorProviderInterface) {
            $e = new InternalServerErrorException();
        }

        $errors = $e->getJsonApiErrors();
        $status = $e->getJsonApiStatus();

        $document = new ErrorDocument(...$errors);

        return json_api_response($document, $status);
    }

    /**
     * Get the base path for the API.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Strip the API's base path from the start of the given path.
     */
    public function stripBasePath(string $path): string
    {
        $basePath = parse_url($this->basePath, PHP_URL_PATH);

        $len = strlen($basePath);

        if (substr($path, 0, $len) === $basePath) {
            $path = substr($path, $len);
        }

        return $path;
    }
}
