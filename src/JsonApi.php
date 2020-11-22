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
use Tobyz\JsonApiServer\Exception\BadRequestException;
use Tobyz\JsonApiServer\Exception\InternalServerErrorException;
use Tobyz\JsonApiServer\Exception\MethodNotAllowedException;
use Tobyz\JsonApiServer\Exception\NotAcceptableException;
use Tobyz\JsonApiServer\Exception\NotImplementedException;
use Tobyz\JsonApiServer\Exception\ResourceNotFoundException;
use Tobyz\JsonApiServer\Exception\UnsupportedMediaTypeException;
use Tobyz\JsonApiServer\Endpoint\Concerns\FindsResources;
use Tobyz\JsonApiServer\Http\MediaTypes;
use Tobyz\JsonApiServer\Schema\Concerns\HasMeta;
use Tobyz\JsonApiServer\Context;

final class JsonApi implements RequestHandlerInterface
{
    const MEDIA_TYPE = 'application/vnd.api+json';

    use FindsResources;
    use HasMeta;

    private $resources = [];
    private $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Define a new resource type.
     */
    public function resource(string $type, AdapterInterface $adapter, callable $buildSchema = null): void
    {
        $this->resources[$type] = new ResourceType($type, $adapter, $buildSchema);
    }

    /**
     * Get defined resource types.
     *
     * @return ResourceType[]
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Get a resource type.
     *
     * @throws ResourceNotFoundException if the resource type has not been defined.
     */
    public function getResource(string $type): ResourceType
    {
        if (! isset($this->resources[$type])) {
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
     * @throws NotImplementedException
     */
    public function handle(Request $request): Response
    {
        $this->validateRequest($request);

        $path = $this->stripBasePath(
            $request->getUri()->getPath()
        );

        $segments = explode('/', trim($path, '/'));
        $resource = $this->getResource($segments[0]);
        $context = new Context($request);

        switch (count($segments)) {
            case 1:
                return $this->handleCollection($context, $resource);

            case 2:
                return $this->handleResource($context, $resource, $segments[1]);

            case 3:
                throw new NotImplementedException;

            case 4:
                if ($segments[2] === 'relationships') {
                    throw new NotImplementedException;
                }
        }

        throw new BadRequestException;
    }

    private function validateRequest(Request $request): void
    {
        $this->validateRequestContentType($request);
        $this->validateRequestAccepts($request);
    }

    private function validateRequestContentType(Request $request): void
    {
        $header = $request->getHeaderLine('Content-Type');

        if (empty($header)) {
            return;
        }

        if ((new MediaTypes($header))->containsExactly(self::MEDIA_TYPE)) {
            return;
        }

        throw new UnsupportedMediaTypeException;
    }

    private function validateRequestAccepts(Request $request): void
    {
        $header = $request->getHeaderLine('Accept');

        if (empty($header)) {
            return;
        }

        $mediaTypes = new MediaTypes($header);

        if ($mediaTypes->containsExactly('*/*') || $mediaTypes->containsExactly(self::MEDIA_TYPE)) {
            return;
        }

        throw new NotAcceptableException;
    }

    private function stripBasePath(string $path): string
    {
        $basePath = parse_url($this->basePath, PHP_URL_PATH);

        $len = strlen($basePath);

        if (substr($path, 0, $len) === $basePath) {
            $path = substr($path, $len + 1);
        }

        return $path;
    }

    private function handleCollection(Context $context, ResourceType $resource): Response
    {
        switch ($context->getRequest()->getMethod()) {
            case 'GET':
                return (new Endpoint\Index($this, $resource))->handle($context);

            case 'POST':
                return (new Endpoint\Create($this, $resource))->handle($context);

            default:
                throw new MethodNotAllowedException;
        }
    }

    private function handleResource(Context $context, ResourceType $resource, string $id): Response
    {
        $model = $this->findResource($resource, $id, $context);

        switch ($context->getRequest()->getMethod()) {
            case 'PATCH':
                return (new Endpoint\Update($this, $resource, $model))->handle($context);

            case 'GET':
                return (new Endpoint\Show($this, $resource, $model))->handle($context);

            case 'DELETE':
                return (new Endpoint\Delete($this, $resource, $model))->handle($context);

            default:
                throw new MethodNotAllowedException;
        }
    }

    /**
     * Convert an exception into a JSON:API error document response.
     *
     * If the exception is not an instance of ErrorProviderInterface, an
     * Internal Server Error response will be produced.
     */
    public function error($e)
    {
        if (! $e instanceof ErrorProviderInterface) {
            $e = new InternalServerErrorException;
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
}
