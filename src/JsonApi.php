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
        // $this->validateRequest($request);

        $context = new Context($this, $request);

        foreach ($this->extensions as $extension) {
            if ($response = $extension->handle($context)) {
                return $response;
            }
        }

        // TODO: apply Vary: Accept header to response

        $path = $this->stripBasePath(
            $request->getUri()->getPath()
        );

        $segments = explode('/', trim($path, '/'));
        $resourceType = $this->getResourceType($segments[0]);

        switch (count($segments)) {
            case 1:
                return $this->handleCollection($context, $resourceType);

            case 2:
                return $this->handleResource($context, $resourceType, $segments[1]);

            case 3:
                throw new NotImplementedException();

            case 4:
                if ($segments[2] === 'relationships') {
                    throw new NotImplementedException();
                }
        }

        throw new BadRequestException();
    }

    private function validateRequest(Request $request): void
    {
        // TODO

        // split content type
        // ensure type is json-api
        // ensure no params other than ext/profile
        // ensure no ext other than those supported
        // return list of ext/profiles to apply

        if ($accept = $request->getHeaderLine('Accept')) {
            $types = array_map('trim', explode(',', $accept));

            foreach ($types as $type) {
                $parts = array_map('trim', explode(';', $type));
            }
        }

        // if accept present
        // split accept, order by qvalue
        // for each media type:
        // if type is not json-api, continue
        // if any params other than ext/profile, continue
        // if any ext other than those supported, continue
        // return list of ext/profiles to apply
        // if none matching, Not Acceptable
    }

    // private function validateRequestContentType(Request $request): void
    // {
    //     $header = $request->getHeaderLine('Content-Type');
    //
    //     if ((new MediaTypes($header))->containsWithOptionalParameters(self::MEDIA_TYPE, ['ext'])) {
    //         return;
    //     }
    //
    //     throw new UnsupportedMediaTypeException;
    // }
    //
    // private function getAcceptedParameters(Request $request): array
    // {
    //     $header = $request->getHeaderLine('Accept');
    //
    //     if (empty($header)) {
    //         return [];
    //     }
    //
    //     $mediaTypes = new MediaTypes($header);
    //
    //     if ($parameters = $mediaTypes->get(self::MEDIA_TYPE, ['ext', 'profile'])) {
    //         return $parameters;
    //     }
    //
    //     throw new NotAcceptableException;
    // }

    private function handleCollection(Context $context, ResourceType $resourceType): Response
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

    private function handleResource(Context $context, ResourceType $resourceType, string $id): Response
    {
        $model = $this->findResource($resourceType, $id, $context);

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
